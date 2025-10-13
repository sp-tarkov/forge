<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TrackingEventType;
use App\Facades\Track;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mchev\Banhammer\Models\Ban;
use stdClass;

class UserManagement extends Component
{
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
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Administrator privileges required.');
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

        return $query
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(25);
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
            flash()->error('Cannot ban other administrators.');

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
                $item->is_banned = Ban::query()->where('ip', $item->ip)
                    ->whereNull('deleted_at')
                    ->where(function (Builder $query): void {
                        $query->whereNull('expired_at')
                            ->orWhere('expired_at', '>', now());
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
            flash()->error('Cannot ban other administrators.');
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

        $existingBan = Ban::query()->where('ip', $ip)
            ->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>', now());
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
        if (! empty($this->search)) {
            $filters[] = sprintf("Search: '%s'", $this->search);
        }

        // Role filter
        if (! empty($this->roleFilter)) {
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
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.admin.user-management')->layout('components.layouts.base', [
            'title' => 'User Management - The Forge',
            'description' => 'Comprehensive user management interface for administrators.',
        ]);
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
        if (! empty($this->search)) {
            $query->where(function (Builder $q): void {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('id', 'like', '%'.$this->search.'%');
            });
        }

        // Role filter
        if (! empty($this->roleFilter)) {
            $query->whereHas('role', function (Builder $q): void {
                $q->where('id', $this->roleFilter);
            });
        }

        // Status filter
        if ($this->statusFilter === 'active') {
            $query->whereDoesntHave('bans', function (Builder $q): void {
                $q->where(function (Builder $subQ): void {
                    $subQ->whereNull('expired_at')
                        ->orWhere('expired_at', '>', now());
                })->whereNull('deleted_at');
            });
        } elseif ($this->statusFilter === 'banned') {
            $query->whereHas('bans', function (Builder $q): void {
                $q->where(function (Builder $subQ): void {
                    $subQ->whereNull('expired_at')
                        ->orWhere('expired_at', '>', now());
                })->whereNull('deleted_at');
            });
        }

        // Date range filters
        if ($this->joinedFrom) {
            $query->where('created_at', '>=', $this->joinedFrom.' 00:00:00');
        }

        if ($this->joinedTo) {
            $query->where('created_at', '<=', $this->joinedTo.' 23:59:59');
        }
    }
}
