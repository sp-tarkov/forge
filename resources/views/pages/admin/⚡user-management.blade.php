<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mchev\Banhammer\Models\Ban;

new #[Layout('layouts::base')] #[Title('User Management - The Forge')] class extends Component {
    use WithPagination;

    /**
     * Filter properties.
     */
    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public ?string $joinedFrom = null;

    public ?string $joinedTo = null;

    /**
     * Sorting configuration.
     */
    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    /**
     * Modal states.
     */
    public bool $showBanModal = false;

    public bool $showUnbanModal = false;

    public bool $showIpModal = false;

    public ?int $selectedUserId = null;

    /**
     * Ban form data.
     */
    public string $banReason = '';

    public string $banDuration = '';

    public ?string $banExpiry = null;

    /**
     * IP addresses data.
     *
     * @var array<int, stdClass>
     */
    public array $userIpAddresses = [];

    /**
     * Initialize the component and set default values.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Staff privileges required.');
    }

    /**
     * Get paginated users based on current filters.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['role', 'bans', 'oAuthConnections'])
            ->withCount(['mods as mods_count', 'authoredComments as comments_count']);

        $this->applyFilters($query);

        return $query->orderBy($this->sortBy, $this->sortDirection)->paginate(25);
    }

    /**
     * Get available user roles for filter dropdown.
     *
     * @return Collection<int, UserRole>
     */
    #[Computed]
    public function roles(): Collection
    {
        return UserRole::query()->orderBy('name')->get();
    }

    /**
     * Toggle sorting by the specified field.
     * Three-state sorting: desc -> asc -> reset to default
     */
    public function sortByColumn(string $field): void
    {
        if ($this->sortBy === $field) {
            if ($this->sortDirection === 'desc') {
                $this->sortDirection = 'asc';
            } elseif ($this->sortDirection === 'asc') {
                // Reset to default
                $this->sortBy = 'created_at';
                $this->sortDirection = 'desc';
            }
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    /**
     * Reset all filters to their default values.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->statusFilter = '';
        $this->joinedFrom = null;
        $this->joinedTo = null;
        $this->resetPage();
    }

    /**
     * Show ban modal for specific user.
     */
    public function showBanUser(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        // Prevent banning other administrators
        if ($user->isAdmin()) {
            flash()->error('Cannot ban other staff members.');

            return;
        }

        $this->selectedUserId = $userId;
        $this->banReason = '';
        $this->banDuration = '';
        $this->banExpiry = null;
        $this->showBanModal = true;
    }

    /**
     * Show unban confirmation modal for specific user.
     */
    public function showUnbanUser(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->showUnbanModal = true;
    }

    /**
     * Show IP addresses modal for specific user.
     */
    public function showUserIpAddresses(int $userId): void
    {
        $this->selectedUserId = $userId;

        // Get IP addresses from tracking events and bans
        $ipData = DB::table('tracking_events')
            ->select('ip', DB::raw('MIN(created_at) as first_seen'), DB::raw('MAX(created_at) as last_seen'), DB::raw('COUNT(*) as usage_count'))
            ->where('visitor_id', $userId)
            ->whereNotNull('ip')
            ->groupBy('ip')
            ->orderByDesc('last_seen')
            ->get()
            ->map(function (stdClass $item): stdClass {
                $item->is_banned = Ban::query()
                    ->where('ip', $item->ip)
                    ->whereNull('deleted_at')
                    ->where(function (Builder $query): void {
                        $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
                    })
                    ->exists();

                return $item;
            })
            ->toArray();

        $this->userIpAddresses = $ipData;
        $this->showIpModal = true;
    }

    /**
     * Ban the selected user.
     */
    public function banUser(): void
    {
        $this->validate([
            'banReason' => 'nullable|string|max:255',
            'banDuration' => 'required|string|in:1_hour,24_hours,7_days,30_days,permanent',
        ]);

        $user = User::query()->findOrFail($this->selectedUserId);

        // Prevent banning other administrators
        if ($user->isAdmin()) {
            flash()->error('Cannot ban other staff members.');
            $this->closeBanModal();

            return;
        }

        $attributes = [
            'created_by_type' => User::class,
            'created_by_id' => auth()->id(),
            'comment' => $this->banReason ?: null,
        ];

        // Set expiration based on duration
        if ($this->banDuration !== 'permanent') {
            $attributes['expired_at'] = $this->getExpirationDate();
        }

        $user->ban($attributes);

        Track::event(TrackingEventType::USER_BAN, $user);

        flash()->success(sprintf('User %s has been banned successfully.', $user->name));
        $this->closeBanModal();
    }

    /**
     * Unban the selected user.
     */
    public function unbanUser(): void
    {
        $user = User::query()->findOrFail($this->selectedUserId);
        $user->unban();

        Track::event(TrackingEventType::USER_UNBAN, $user);

        flash()->success(sprintf('User %s has been unbanned successfully.', $user->name));
        $this->closeUnbanModal();
    }

    /**
     * Ban or unban an IP address.
     */
    public function toggleIpBan(string $ip): void
    {
        // Prevent banning the user's current IP address
        if ($ip === request()->ip()) {
            flash()->error('Cannot ban your current IP address.');

            return;
        }

        $existingBan = Ban::query()
            ->where('ip', $ip)
            ->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->first();

        if ($existingBan) {
            // Unban IP
            $existingBan->delete();
            Track::event(TrackingEventType::IP_UNBAN, additionalData: ['ip' => $ip]);
            flash()->success(sprintf('IP address %s has been unbanned.', $ip));
        } else {
            // Ban IP with 1 month expiry
            Ban::query()->create([
                'bannable_type' => null,
                'bannable_id' => null,
                'created_by_type' => User::class,
                'created_by_id' => auth()->id(),
                'comment' => 'IP banned from user management (expires in 1 month)',
                'ip' => $ip,
                'expired_at' => now()->addMonth(),
            ]);
            Track::event(TrackingEventType::IP_BAN, additionalData: ['ip' => $ip]);
            flash()->success(sprintf('IP address %s has been banned for 1 month.', $ip));
        }

        // Refresh IP data if we have a selected user
        if ($this->selectedUserId !== null) {
            $this->showUserIpAddresses($this->selectedUserId);
        }
    }

    /**
     * Close ban modal.
     */
    public function closeBanModal(): void
    {
        $this->showBanModal = false;
        $this->selectedUserId = null;
        $this->banReason = '';
        $this->banDuration = '';
        $this->banExpiry = null;
    }

    /**
     * Close unban modal.
     */
    public function closeUnbanModal(): void
    {
        $this->showUnbanModal = false;
        $this->selectedUserId = null;
    }

    /**
     * Close IP modal.
     */
    public function closeIpModal(): void
    {
        $this->showIpModal = false;
        $this->selectedUserId = null;
        $this->userIpAddresses = [];
    }

    /**
     * Reset pagination when filter properties are updated.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedJoinedFrom(): void
    {
        $this->resetPage();
    }

    public function updatedJoinedTo(): void
    {
        $this->resetPage();
    }

    /**
     * Get the active filters for breadcrumb display.
     *
     * @return array<int, string>
     */
    public function getActiveFilters(): array
    {
        $filters = [];

        // Search filter
        if (!empty($this->search)) {
            $filters[] = sprintf("Search: '%s'", $this->search);
        }

        // Role filter
        if (!empty($this->roleFilter)) {
            $role = $this->roles()->firstWhere('id', $this->roleFilter);
            if ($role) {
                $filters[] = sprintf('Role: %s', $role->name);
            }
        }

        // Status filter
        if ($this->statusFilter === 'active') {
            $filters[] = 'Active users only';
        } elseif ($this->statusFilter === 'banned') {
            $filters[] = 'Banned users only';
        }

        // Date range filters
        if ($this->joinedFrom && $this->joinedTo) {
            $fromDate = Date::parse($this->joinedFrom)->format('M j, Y');
            $toDate = Date::parse($this->joinedTo)->format('M j, Y');
            $filters[] = sprintf('Joined: %s - %s', $fromDate, $toDate);
        } elseif ($this->joinedFrom) {
            $fromDate = Date::parse($this->joinedFrom)->format('M j, Y');
            $filters[] = sprintf('Joined after: %s', $fromDate);
        } elseif ($this->joinedTo) {
            $toDate = Date::parse($this->joinedTo)->format('M j, Y');
            $filters[] = sprintf('Joined before: %s', $toDate);
        }

        // Sorting information
        if ($this->sortBy !== 'created_at' || $this->sortDirection !== 'desc') {
            $sortFieldName = match ($this->sortBy) {
                'name' => 'Name',
                'user_role_id' => 'Role',
                'email_verified_at' => 'Email Status',
                'created_at' => 'Joined Date',
                'email' => 'Email',
                default => ucfirst($this->sortBy),
            };
            $sortDirection = $this->sortDirection === 'desc' ? 'descending' : 'ascending';
            $filters[] = sprintf('Sorted by: %s (%s)', $sortFieldName, $sortDirection);
        }

        return $filters;
    }

    /**
     * Get the selected user for modals.
     */
    public function getSelectedUserProperty(): ?User
    {
        return $this->selectedUserId ? User::with(['role', 'bans'])->find($this->selectedUserId) : null;
    }

    /**
     * Get the available ban duration options for the modal.
     *
     * @return array<string, string> Array of duration keys and display labels
     */
    public function getDurationOptions(): array
    {
        return [
            '1_hour' => '1 Hour',
            '24_hours' => '24 Hours',
            '7_days' => '7 Days',
            '30_days' => '30 Days',
            'permanent' => 'Permanent',
        ];
    }

    /**
     * Calculate the expiration date based on the selected duration.
     */
    protected function getExpirationDate(): Carbon
    {
        return match ($this->banDuration) {
            '1_hour' => now()->addHour(),
            '24_hours' => now()->addDay(),
            '7_days' => now()->addWeek(),
            '30_days' => now()->addMonth(),
            default => now()->addHour(),
        };
    }

    /**
     * Apply all active filters to the given query.
     *
     * @param  Builder<User>  $query
     */
    private function applyFilters(Builder $query): void
    {
        // Search filter
        if (!empty($this->search)) {
            $query->where(function (Builder $q): void {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhere('id', 'like', '%' . $this->search . '%');
            });
        }

        // Role filter
        if (!empty($this->roleFilter)) {
            $query->whereHas('role', function (Builder $q): void {
                $q->where('id', $this->roleFilter);
            });
        }

        // Status filter
        if ($this->statusFilter === 'active') {
            $query->whereDoesntHave('bans', function (Builder $q): void {
                $q->where(function (Builder $subQ): void {
                    $subQ->whereNull('expired_at')->orWhere('expired_at', '>', now());
                })->whereNull('deleted_at');
            });
        } elseif ($this->statusFilter === 'banned') {
            $query->whereHas('bans', function (Builder $q): void {
                $q->where(function (Builder $subQ): void {
                    $subQ->whereNull('expired_at')->orWhere('expired_at', '>', now());
                })->whereNull('deleted_at');
            });
        }

        // Date range filters
        if ($this->joinedFrom) {
            $query->where('created_at', '>=', $this->joinedFrom . ' 00:00:00');
        }

        if ($this->joinedTo) {
            $query->where('created_at', '<=', $this->joinedTo . ' 23:59:59');
        }
    }
};
?>

@php
    use Illuminate\Support\Str;
@endphp

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
                    {{ __('User Management') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        @if ($this->getActiveFilters())
            <div class="my-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-shrink-0">Filtering:</span>
                    <div class="min-w-0">
                        <flux:breadcrumbs class="inline-flex flex-wrap">
                            @foreach ($this->getActiveFilters() as $index => $filter)
                                <flux:breadcrumbs.item separator="slash">{{ $filter }}</flux:breadcrumbs.item>
                            @endforeach
                        </flux:breadcrumbs>
                    </div>
                </div>
            </div>
        @endif

        {{-- Flash Messages --}}
        @if (session()->has('success'))
            <div
                class="mb-6 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <div class="flex items-center">
                    <flux:icon.check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" />
                    <p class="text-green-800 dark:text-green-200 text-sm">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                <div class="flex items-center">
                    <flux:icon.x-circle class="w-5 h-5 text-red-600 dark:text-red-400 mr-2" />
                    <p class="text-red-800 dark:text-red-200 text-sm">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <div class="space-y-6">
            {{-- Filters Section --}}
            <div
                id="filters-container"
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
                x-data="{
                    search: $wire.entangle('search').live,
                    clearSearch() {
                        this.search = '';
                    }
                }"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="resetFilters"
                        x-on:click="clearSearch()"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear All
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                    {{-- Search Filter --}}
                    <div wire:ignore.self>
                        <flux:label
                            for="search"
                            class="text-xs"
                        >Search</flux:label>
                        <flux:input
                            type="text"
                            x-model.debounce.300ms="search"
                            id="search"
                            placeholder="Name, email, or ID..."
                            size="sm"
                        />
                    </div>

                    {{-- Role Filter --}}
                    <div>
                        <flux:label
                            for="roleFilter"
                            class="text-xs"
                        >Role</flux:label>
                        <flux:select
                            wire:model.live="roleFilter"
                            id="roleFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Roles</flux:select.option>
                            @foreach ($this->roles as $role)
                                <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Status Filter --}}
                    <div>
                        <flux:label
                            for="statusFilter"
                            class="text-xs"
                        >Status</flux:label>
                        <flux:select
                            wire:model.live="statusFilter"
                            id="statusFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Users</flux:select.option>
                            <flux:select.option value="active">Active Only</flux:select.option>
                            <flux:select.option value="banned">Banned Only</flux:select.option>
                        </flux:select>
                    </div>

                    {{-- Date Range Filters --}}
                    <div>
                        <flux:label
                            for="joinedFrom"
                            class="text-xs"
                        >Joined From</flux:label>
                        <flux:input
                            type="date"
                            wire:model.live="joinedFrom"
                            id="joinedFrom"
                            size="sm"
                        />
                    </div>

                    <div>
                        <flux:label
                            for="joinedTo"
                            class="text-xs"
                        >Joined To</flux:label>
                        <flux:input
                            type="date"
                            wire:model.live="joinedTo"
                            id="joinedTo"
                            size="sm"
                        />
                    </div>
                </div>
            </div>

            {{-- Users Table --}}
            <div
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Users
                        ({{ number_format($this->users->total()) }})</h3>
                </div>

                {{-- Top Pagination --}}
                @if ($this->users->hasPages())
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        {{ $this->users->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table
                        class="w-full table-auto"
                        style="min-width: 1000px;"
                    >
                        <thead class="bg-gray-100 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('name')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>User</span>
                                        @if ($sortBy === 'name')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('user_role_id')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Role</span>
                                        @if ($sortBy === 'user_role_id')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Ban Status</th>
                                <th
                                    class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('email_verified_at')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Email</span>
                                        @if ($sortBy === 'email_verified_at')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 sm:px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    MFA</th>
                                <th
                                    class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Content</th>
                                <th
                                    class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('created_at')"
                                        class="flex items-center space-x-1 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300 select-none w-full text-left"
                                    >
                                        <span>Joined</span>
                                        @if ($sortBy === 'created_at')
                                            <flux:icon.chevron-down
                                                class="w-3 h-3 {{ $sortDirection === 'desc' ? '' : 'rotate-180' }}"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 sm:px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->users as $user)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    {{-- User Column --}}
                                    <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                                        <div class="flex items-center space-x-2">
                                            <flux:avatar
                                                circle="circle"
                                                src="{{ $user->profile_photo_url }}"
                                                color="auto"
                                                color:seed="{{ $user->id }}"
                                                size="sm"
                                            />
                                            <div class="min-w-0">
                                                <a
                                                    href="{{ $user->profile_url }}"
                                                    class="text-sm font-medium underline hover:text-gray-600 dark:hover:text-gray-300 truncate block max-w-32 lg:max-w-48"
                                                >
                                                    <x-user-name :user="$user" />
                                                </a>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $user->email }}</p>
                                                <p class="text-xs text-gray-400 dark:text-gray-500">ID:
                                                    {{ $user->id }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Role Column --}}
                                    <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                                        @if ($user->role)
                                            <flux:badge
                                                color="{{ $user->role->color_class }}"
                                                size="sm"
                                            >
                                                {{ $user->role->short_name }}
                                            </flux:badge>
                                        @else
                                            <flux:badge
                                                color="gray"
                                                size="sm"
                                            >User</flux:badge>
                                        @endif
                                    </td>

                                    {{-- Ban Status Column --}}
                                    <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                                        @php
                                            $activeBan = $user->bans
                                                ->where('deleted_at', null)
                                                ->where(function ($ban) {
                                                    return is_null($ban->expired_at) || $ban->expired_at > now();
                                                })
                                                ->first();
                                        @endphp

                                        @if ($activeBan)
                                            <div class="flex items-center gap-2">
                                                <flux:badge
                                                    color="red"
                                                    size="sm"
                                                >Banned</flux:badge>
                                                <flux:tooltip>
                                                    <flux:icon.information-circle
                                                        class="w-4 h-4 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 cursor-help"
                                                    />
                                                    <flux:tooltip.content>
                                                        <div class="text-sm">
                                                            <div class="font-medium">Ban Details</div>
                                                            <div class="mt-1">Reason:
                                                                {{ $activeBan->comment ?: 'No reason provided' }}</div>
                                                            <div>Date:
                                                                {{ $activeBan->created_at->format('M j, Y g:i A') }}
                                                            </div>
                                                            <div>Expires:
                                                                {{ $activeBan->expired_at ? $activeBan->expired_at->format('M j, Y g:i A') : 'Permanent' }}
                                                            </div>
                                                        </div>
                                                    </flux:tooltip.content>
                                                </flux:tooltip>
                                            </div>
                                        @else
                                            <flux:badge
                                                color="green"
                                                size="sm"
                                            >Active</flux:badge>
                                        @endif
                                    </td>

                                    {{-- Email Column --}}
                                    <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            @if ($user->email_verified_at)
                                                <flux:badge
                                                    color="green"
                                                    size="sm"
                                                >Verified</flux:badge>
                                            @else
                                                <flux:badge
                                                    color="red"
                                                    size="sm"
                                                >Unverified</flux:badge>
                                            @endif
                                            @if ($user->hasDisposableEmail())
                                                <flux:tooltip>
                                                    <flux:icon.exclamation-triangle
                                                        class="w-4 h-4 text-amber-600 dark:text-amber-400"
                                                    />
                                                    <flux:tooltip.content>
                                                        <div class="text-sm">
                                                            This email address is flagged as being disposable
                                                        </div>
                                                    </flux:tooltip.content>
                                                </flux:tooltip>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- MFA Column --}}
                                    <td class="px-2 sm:px-3 py-2 whitespace-nowrap text-center">
                                        @if ($user->hasMfaEnabled())
                                            <flux:icon.shield-check
                                                class="w-4 h-4 text-green-600 dark:text-green-400 mx-auto"
                                            />
                                        @else
                                            <flux:icon.shield-exclamation
                                                class="w-4 h-4 text-red-600 dark:text-red-400 mx-auto"
                                            />
                                        @endif
                                    </td>

                                    {{-- Content Column --}}
                                    <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                                        <div class="text-xs text-gray-900 dark:text-gray-100">
                                            <div>Mods: {{ number_format($user->mods_count) }}</div>
                                            <div>Comments: {{ number_format($user->comments_count) }}</div>
                                        </div>
                                    </td>

                                    {{-- Joined Column --}}
                                    <td
                                        class="px-2 sm:px-3 py-2 whitespace-nowrap text-xs text-gray-900 dark:text-gray-100">
                                        <div>{{ $user->created_at->format('M j, Y') }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">
                                            {{ $user->created_at->diffForHumans() }}</div>
                                    </td>

                                    {{-- Actions Column --}}
                                    <td class="px-2 sm:px-3 py-2 whitespace-nowrap">
                                        <flux:dropdown align="end">
                                            <flux:button
                                                variant="outline"
                                                size="xs"
                                                icon="ellipsis-horizontal"
                                            >
                                                Actions
                                            </flux:button>
                                            <flux:menu>
                                                <flux:menu.item
                                                    icon="eye"
                                                    wire:navigate
                                                    href="{{ $user->profile_url }}"
                                                >
                                                    View Profile
                                                </flux:menu.item>
                                                <flux:menu.item
                                                    icon="computer-desktop"
                                                    wire:click="showUserIpAddresses({{ $user->id }})"
                                                >
                                                    View IP Addresses
                                                </flux:menu.item>
                                                @if (!$user->isAdmin())
                                                    <flux:menu.separator />
                                                    @if ($activeBan)
                                                        <flux:menu.item
                                                            icon="check-circle"
                                                            wire:click="showUnbanUser({{ $user->id }})"
                                                        >
                                                            Unban User
                                                        </flux:menu.item>
                                                    @else
                                                        <flux:menu.item
                                                            icon="no-symbol"
                                                            wire:click="showBanUser({{ $user->id }})"
                                                        >
                                                            Ban User
                                                        </flux:menu.item>
                                                    @endif
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="8"
                                        class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        <flux:icon.users
                                            class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600"
                                        />
                                        <p class="text-gray-500 dark:text-gray-400">No users found for the selected
                                            filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Pagination --}}
                @if ($this->users->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $this->users->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Ban User Modal --}}
    <flux:modal
        wire:model.self="showBanModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="shield-exclamation"
                        class="w-8 h-8 text-red-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Ban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Restrict user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-6">
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon
                            name="exclamation-triangle"
                            class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"
                        />
                        <div>
                            <flux:text class="text-red-800 dark:text-red-200 text-sm font-medium">
                                {{ __('Warning') }}
                            </flux:text>
                            <flux:text class="text-red-700 dark:text-red-300 text-sm mt-1">
                                {{ __('Banned users cannot access the platform when logged in, but may still access content when logged out.') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div>
                    <flux:radio.group
                        wire:model.live="banDuration"
                        label="{{ __('Ban Duration') }}"
                        class="text-left"
                    >
                        @foreach ($this->getDurationOptions() as $value => $label)
                            <flux:radio
                                value="{{ $value }}"
                                label="{{ $label }}"
                            />
                        @endforeach
                    </flux:radio.group>
                    @error('banDuration')
                        <flux:text
                            size="xs"
                            variant="danger"
                            class="mt-1"
                        >{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:textarea
                        wire:model.live="banReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Please provide a reason for this ban...') }}"
                        rows="3"
                    />
                    @error('banReason')
                        <flux:text
                            size="xs"
                            variant="danger"
                            class="mt-1"
                        >{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <flux:icon
                        name="information-circle"
                        class="w-4 h-4 mr-2 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        {{ __('This action can be reversed by unbanning the user') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button
                        wire:click="closeBanModal"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="banUser"
                        variant="danger"
                        size="sm"
                        icon="shield-exclamation"
                    >
                        {{ __('Ban User') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Unban User Modal --}}
    <flux:modal
        wire:model.self="showUnbanModal"
        class="md:w-[400px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="shield-check"
                        class="w-8 h-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Unban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Restore user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300">
                    {{ __('Are you sure you want to unban this user? They will regain full access to the platform.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button
                    wire:click="closeUnbanModal"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="unbanUser"
                    variant="primary"
                    size="sm"
                    icon="shield-check"
                >
                    {{ __('Unban User') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- IP Addresses Modal --}}
    <flux:modal
        wire:model.self="showIpModal"
        variant="flyout"
    >
        <div class="space-y-6">
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="computer-desktop"
                        class="w-8 h-8 text-blue-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            IP Addresses
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            @if ($this->selectedUser)
                                IP addresses used by {{ $this->selectedUser->name }}
                            @endif
                        </flux:text>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                {{-- IP Blocking Warning --}}
                <flux:callout
                    variant="warning"
                    icon="exclamation-triangle"
                >
                    <flux:callout.heading>Important</flux:callout.heading>
                    <flux:callout.text>
                        <p class="mb-2">IP blocking should only be used in extreme cases, such as harassment or abuse
                            situations. IP addresses may be shared among multiple users, masked by VPNs, or changed
                            easily by users.</p>
                        <p><strong>Temporary blocks:</strong> IP bans are automatically set to expire after one month
                            and should not be relied upon as a permanent solution.</p>
                    </flux:callout.text>
                </flux:callout>

                @forelse($userIpAddresses as $ipData)
                    <div
                        class="flex items-center justify-between p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-sm text-gray-900 dark:text-gray-100">{{ $ipData->ip }}</span>
                                @if ($ipData->is_banned)
                                    <flux:badge
                                        color="red"
                                        size="sm"
                                    >Banned</flux:badge>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <div>First seen:
                                    {{ \Carbon\Carbon::parse($ipData->first_seen)->format('M j, Y g:i A') }}</div>
                                <div>Last seen: {{ \Carbon\Carbon::parse($ipData->last_seen)->format('M j, Y g:i A') }}
                                </div>
                                <div>Usage count: {{ number_format($ipData->usage_count) }}</div>
                            </div>
                        </div>
                        <div>
                            @if ($ipData->ip === request()->ip())
                                <flux:tooltip>
                                    <div class="inline-block">
                                        <flux:button
                                            variant="{{ $ipData->is_banned ? 'primary' : 'danger' }}"
                                            size="xs"
                                            disabled
                                        >
                                            {{ $ipData->is_banned ? 'Unban IP' : 'Ban IP' }}
                                        </flux:button>
                                    </div>
                                    <flux:tooltip.content>
                                        <div class="text-sm">
                                            <div class="font-medium">Cannot ban current IP</div>
                                            <div class="mt-1">This is your current IP address. Banning it would lock
                                                you out of the system.</div>
                                        </div>
                                    </flux:tooltip.content>
                                </flux:tooltip>
                            @else
                                <flux:button
                                    wire:click="toggleIpBan('{{ $ipData->ip }}')"
                                    variant="{{ $ipData->is_banned ? 'primary' : 'danger' }}"
                                    size="xs"
                                >
                                    {{ $ipData->is_banned ? 'Unban IP' : 'Ban IP' }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <flux:icon.computer-desktop class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" />
                        <p class="text-gray-500 dark:text-gray-400">No IP addresses found for this user.</p>
                    </div>
                @endforelse
            </div>

            <div class="flex justify-end items-center pt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button
                    wire:click="closeIpModal"
                    variant="outline"
                >
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
