<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] #[Title('Role Management - The Forge')] class extends Component {
    use WithPagination;

    /**
     * Search input for finding users to assign roles.
     */
    public string $userSearch = '';

    /**
     * Whether the user search dropdown is visible.
     */
    public bool $showUserDropdown = false;

    /**
     * Role filter for the users with roles table.
     */
    public string $roleFilter = '';

    /**
     * Modal states.
     */
    public bool $showAssignModal = false;

    public bool $showRemoveModal = false;

    /**
     * Selected user ID for role assignment.
     */
    public ?int $selectedUserId = null;

    /**
     * Selected role ID for assignment.
     */
    public ?int $selectedRoleId = null;

    /**
     * User ID for role removal.
     */
    public ?int $userToRemoveRoleId = null;

    /**
     * Initialize the component and verify admin access.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Staff privileges required.');
    }

    /**
     * Get paginated list of users who have roles assigned.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function usersWithRoles(): LengthAwarePaginator
    {
        $query = User::query()->with('role')->whereNotNull('user_role_id');

        if (!empty($this->roleFilter)) {
            $query->where('user_role_id', $this->roleFilter);
        }

        return $query->orderBy('name')->paginate(25);
    }

    /**
     * Get all available roles.
     *
     * @return Collection<int, UserRole>
     */
    #[Computed]
    public function roles(): Collection
    {
        return UserRole::query()->orderBy('name')->get();
    }

    /**
     * Get search results for user assignment.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function searchResults(): Collection
    {
        if (mb_strlen(mb_trim($this->userSearch)) < 2) {
            return collect();
        }

        return User::query()
            ->where(function (Builder $query): void {
                $query->where('name', 'like', '%' . $this->userSearch . '%')->orWhere('email', 'like', '%' . $this->userSearch . '%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /**
     * Handle user search input changes.
     */
    public function updatedUserSearch(): void
    {
        $this->showUserDropdown = mb_strlen(mb_trim($this->userSearch)) >= 2;
    }

    /**
     * Handle role filter changes.
     */
    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Open the assign role modal for the selected user.
     */
    public function showAssignRoleModal(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->selectedRoleId = null;
        $this->showAssignModal = true;
        $this->showUserDropdown = false;
    }

    /**
     * Assign the selected role to the selected user.
     */
    public function assignRole(): void
    {
        $this->validate([
            'selectedUserId' => 'required|exists:users,id',
            'selectedRoleId' => 'required|exists:user_roles,id',
        ]);

        $user = User::query()->findOrFail($this->selectedUserId);

        $user->assignRole($this->selectedRoleId);

        // Clear the cached role name
        Cache::forget(sprintf('user_%d_role_name', $user->id));

        flash()->success(sprintf('Role assigned to %s successfully.', $user->name));

        $this->closeAssignModal();
    }

    /**
     * Open the remove role confirmation modal.
     */
    public function showRemoveRoleModal(int $userId): void
    {
        $this->userToRemoveRoleId = $userId;
        $this->showRemoveModal = true;
    }

    /**
     * Remove the role from the selected user.
     */
    public function removeRole(): void
    {
        $user = User::query()->findOrFail($this->userToRemoveRoleId);

        // Prevent admins from removing their own role
        if ($user->id === auth()->id()) {
            flash()->error('You cannot remove your own role.');
            $this->closeRemoveModal();

            return;
        }

        $previousRoleName = $user->role !== null ? $user->role->name : 'unknown';

        $user->user_role_id = null;
        $user->save();

        // Clear the cached role name
        Cache::forget(sprintf('user_%d_role_name', $user->id));

        flash()->success(sprintf('%s role removed from %s successfully.', $previousRoleName, $user->name));

        $this->closeRemoveModal();
    }

    /**
     * Close the assign role modal.
     */
    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->selectedUserId = null;
        $this->selectedRoleId = null;
        $this->userSearch = '';
        $this->showUserDropdown = false;
    }

    /**
     * Close the remove role modal.
     */
    public function closeRemoveModal(): void
    {
        $this->showRemoveModal = false;
        $this->userToRemoveRoleId = null;
    }

    /**
     * Get the selected user for the assign modal.
     */
    public function getSelectedUserProperty(): ?User
    {
        return $this->selectedUserId ? User::query()->with('role')->find($this->selectedUserId) : null;
    }

    /**
     * Get the user for the remove modal.
     */
    public function getUserToRemoveRoleProperty(): ?User
    {
        return $this->userToRemoveRoleId ? User::query()->with('role')->find($this->userToRemoveRoleId) : null;
    }
};
?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
                    {{ __('Role Management') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        <div class="space-y-6">
            {{-- User Search Section --}}
            <div
                id="search-container"
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="flex items-center gap-3 mb-4">
                    <flux:icon.user-plus class="w-6 h-6 text-gray-600 dark:text-gray-400" />
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Assign Role to User') }}
                    </h3>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ __('Search for a user by name or email to assign them a role.') }}
                </p>

                <div class="relative max-w-md">
                    <flux:input
                        type="text"
                        wire:model.live.debounce.300ms="userSearch"
                        placeholder="{{ __('Search users by name or email...') }}"
                        icon="magnifying-glass"
                    />

                    {{-- Search Results Dropdown --}}
                    @if ($showUserDropdown && $this->searchResults->isNotEmpty())
                        <div
                            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-80 overflow-y-auto">
                            @foreach ($this->searchResults as $user)
                                <button
                                    type="button"
                                    wire:key="search-user-{{ $user->id }}"
                                    wire:click="showAssignRoleModal({{ $user->id }})"
                                    class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 text-left transition-colors"
                                >
                                    <flux:avatar
                                        circle
                                        src="{{ $user->profile_photo_url }}"
                                        color="auto"
                                        color:seed="{{ $user->id }}"
                                        size="sm"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                {{ $user->name }}
                                            </span>
                                            @if ($user->role)
                                                <flux:badge
                                                    color="{{ $user->role->color_class }}"
                                                    size="sm"
                                                >{{ $user->role->short_name }}</flux:badge>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                            {{ $user->email }}</p>
                                    </div>
                                    <flux:icon.chevron-right class="w-4 h-4 text-gray-400 flex-shrink-0" />
                                </button>
                            @endforeach
                        </div>
                    @elseif ($showUserDropdown && $this->searchResults->isEmpty())
                        <div
                            class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-4 text-center">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('No users found matching your search.') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Users with Roles Table --}}
            <div
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Users with Roles') }} ({{ number_format($this->usersWithRoles->total()) }})
                        </h3>

                        {{-- Role Filter --}}
                        <div class="w-full sm:w-48">
                            <flux:select
                                wire:model.live="roleFilter"
                                size="sm"
                            >
                                <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
                                @foreach ($this->roles as $role)
                                    <flux:select.option value="{{ $role->id }}">{{ $role->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </div>

                {{-- Top Pagination --}}
                @if ($this->usersWithRoles->hasPages())
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        {{ $this->usersWithRoles->links(data: ['scrollTo' => '#search-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-100 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    {{ __('User') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    {{ __('Role') }}
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($this->usersWithRoles as $user)
                                <tr
                                    wire:key="role-user-{{ $user->id }}"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    {{-- User Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar
                                                circle
                                                src="{{ $user->profile_photo_url }}"
                                                color="auto"
                                                color:seed="{{ $user->id }}"
                                                size="sm"
                                            />
                                            <div class="min-w-0">
                                                <a
                                                    href="{{ $user->profile_url }}"
                                                    class="text-sm font-medium text-gray-900 dark:text-gray-100 hover:text-gray-600 dark:hover:text-gray-300 underline truncate block max-w-48"
                                                    wire:navigate
                                                >
                                                    {{ $user->name }}
                                                </a>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    {{ $user->email }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Role Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($user->role)
                                            <flux:badge
                                                color="{{ $user->role->color_class }}"
                                                size="sm"
                                            >
                                                {{ $user->role->name }}
                                            </flux:badge>
                                        @endif
                                    </td>

                                    {{-- Actions Column --}}
                                    <td class="px-4 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button
                                                wire:click="showAssignRoleModal({{ $user->id }})"
                                                variant="outline"
                                                size="xs"
                                                icon="pencil"
                                            >
                                                {{ __('Change') }}
                                            </flux:button>
                                            <flux:button
                                                wire:click="showRemoveRoleModal({{ $user->id }})"
                                                variant="danger"
                                                size="xs"
                                                icon="trash"
                                            >
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="3"
                                        class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        <flux:icon.user-group
                                            class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600"
                                        />
                                        <p class="text-gray-500 dark:text-gray-400">
                                            {{ __('No users with roles found.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Pagination --}}
                @if ($this->usersWithRoles->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $this->usersWithRoles->links(data: ['scrollTo' => '#search-container']) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Assign Role Modal --}}
    <flux:modal
        wire:model.self="showAssignModal"
        class="md:w-[450px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon.user-plus class="w-8 h-8 text-blue-600" />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Assign Role') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Select a role to assign to this user') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-6">
                {{-- Selected User Info --}}
                @if ($this->selectedUser)
                    <div class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <flux:avatar
                            circle
                            src="{{ $this->selectedUser->profile_photo_url }}"
                            color="auto"
                            color:seed="{{ $this->selectedUser->id }}"
                            size="md"
                        />
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $this->selectedUser->name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->selectedUser->email }}</p>
                            @if ($this->selectedUser->role)
                                <div class="mt-1">
                                    <span
                                        class="text-xs text-gray-500 dark:text-gray-400">{{ __('Current role:') }}</span>
                                    <flux:badge
                                        color="{{ $this->selectedUser->role->color_class }}"
                                        size="sm"
                                    >{{ $this->selectedUser->role->name }}</flux:badge>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Role Selection --}}
                <div>
                    <flux:select
                        wire:model="selectedRoleId"
                        label="{{ __('Select Role') }}"
                    >
                        <flux:select.option value="">{{ __('Choose a role...') }}</flux:select.option>
                        @foreach ($this->roles as $role)
                            <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('selectedRoleId')
                        <flux:text
                            size="xs"
                            variant="danger"
                            class="mt-1"
                        >{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button
                    wire:click="closeAssignModal"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="assignRole"
                    variant="primary"
                    size="sm"
                    icon="check"
                >
                    {{ __('Assign Role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Remove Role Modal --}}
    <flux:modal
        wire:model.self="showRemoveModal"
        class="md:w-[400px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon.exclamation-triangle class="w-8 h-8 text-red-600" />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Remove Role') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Confirm role removal') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                @if ($this->userToRemoveRole)
                    <flux:text class="text-gray-700 dark:text-gray-300">
                        {{ __('Are you sure you want to remove the') }}
                        <flux:badge
                            color="{{ $this->userToRemoveRole->role?->color_class }}"
                            size="sm"
                        >{{ $this->userToRemoveRole->role?->name }}</flux:badge>
                        {{ __('role from') }}
                        <span class="font-medium">{{ $this->userToRemoveRole->name }}</span>?
                    </flux:text>
                    <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('This user will lose all privileges associated with this role.') }}
                    </flux:text>
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button
                    wire:click="closeRemoveModal"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="removeRole"
                    variant="danger"
                    size="sm"
                    icon="trash"
                >
                    {{ __('Remove Role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
