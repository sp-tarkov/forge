<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RoleManagement extends Component
{
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
        abort_unless(auth()->user()?->isAdmin(), 403, 'Access denied. Administrator privileges required.');
    }

    /**
     * Get paginated list of users who have roles assigned.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function usersWithRoles(): LengthAwarePaginator
    {
        $query = User::query()
            ->with('role')
            ->whereNotNull('user_role_id');

        if (! empty($this->roleFilter)) {
            $query->where('user_role_id', $this->roleFilter);
        }

        return $query
            ->orderBy('name')
            ->paginate(25);
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
                $query->where('name', 'like', '%'.$this->userSearch.'%')
                    ->orWhere('email', 'like', '%'.$this->userSearch.'%');
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

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.admin.role-management')->layout('components.layouts.base', [
            'title' => 'Role Management - The Forge',
            'description' => 'Manage user roles and permissions.',
        ]);
    }
}
