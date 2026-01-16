<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

describe('RoleManagement Authorization', function (): void {
    it('denies access to guests', function (): void {
        $this->get(route('admin.role-management'))
            ->assertRedirect(route('login'));
    });

    it('denies access to regular users', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.role-management'))
            ->assertForbidden();
    });

    it('denies access to moderators', function (): void {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator)
            ->get(route('admin.role-management'))
            ->assertForbidden();
    });

    it('allows access to administrators', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.role-management'))
            ->assertOk();
    });
});

describe('RoleManagement Page Display', function (): void {
    it('displays the role management page for admins', function (): void {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->assertStatus(200)
            ->assertSee('Assign Role to User')
            ->assertSee('Users with Roles');
    });

    it('displays users with roles', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->assertSee($moderator->name)
            ->assertSee($admin->name);
    });

    it('displays available roles for filter dropdown', function (): void {
        $admin = User::factory()->admin()->create();
        // Ensure both roles exist by creating a moderator
        User::factory()->moderator()->create();

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.role-management');

        $roles = $component->get('roles');

        expect($roles->count())->toBeGreaterThanOrEqual(2);
        expect($roles->pluck('name')->toArray())->toContain('Staff', 'Moderator');
    });
});

describe('RoleManagement User Search', function (): void {
    it('searches for users by name', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create(['name' => 'SearchableUser']);

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('userSearch', 'SearchableUser')
            ->assertSet('showUserDropdown', true);
    });

    it('searches for users by email', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create(['email' => 'searchable@example.com']);

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('userSearch', 'searchable@example')
            ->assertSet('showUserDropdown', true);
    });

    it('shows dropdown when search has results', function (): void {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'TestSearchUser']);

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('userSearch', 'TestSearch')
            ->assertSet('showUserDropdown', true);
    });

    it('hides dropdown when search is too short', function (): void {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('userSearch', 'a')
            ->assertSet('showUserDropdown', false);
    });

    it('hides dropdown when search is cleared', function (): void {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'TestUser']);

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('userSearch', 'TestUser')
            ->assertSet('showUserDropdown', true)
            ->set('userSearch', '')
            ->assertSet('showUserDropdown', false);
    });
});

describe('RoleManagement Role Assignment', function (): void {
    it('shows assign role modal when user selected from search', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showAssignRoleModal', $targetUser->id)
            ->assertSet('showAssignModal', true)
            ->assertSet('selectedUserId', $targetUser->id);
    });

    it('assigns role to user successfully', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();
        $moderatorRole = UserRole::query()->firstOrCreate(
            ['name' => 'Moderator'],
            ['short_name' => 'Mod', 'description' => 'A moderator', 'color_class' => 'orange', 'icon' => 'wrench']
        );

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showAssignRoleModal', $targetUser->id)
            ->set('selectedRoleId', $moderatorRole->id)
            ->call('assignRole')
            ->assertSet('showAssignModal', false);

        expect($targetUser->fresh()->user_role_id)->toBe($moderatorRole->id);
    });

    it('validates role selection before assignment', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showAssignRoleModal', $targetUser->id)
            ->call('assignRole')
            ->assertHasErrors(['selectedRoleId']);
    });

    it('clears search after assignment', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();
        $moderatorRole = UserRole::query()->firstOrCreate(
            ['name' => 'Moderator'],
            ['short_name' => 'Mod', 'description' => 'A moderator', 'color_class' => 'orange', 'icon' => 'wrench']
        );

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('userSearch', 'Test')
            ->call('showAssignRoleModal', $targetUser->id)
            ->set('selectedRoleId', $moderatorRole->id)
            ->call('assignRole')
            ->assertSet('userSearch', '');
    });

    it('clears cache after role assignment', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();
        $moderatorRole = UserRole::query()->firstOrCreate(
            ['name' => 'Moderator'],
            ['short_name' => 'Mod', 'description' => 'A moderator', 'color_class' => 'orange', 'icon' => 'wrench']
        );

        // Prime the cache
        Cache::put(sprintf('user_%d_role_name', $targetUser->id), 'test', 3600);

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showAssignRoleModal', $targetUser->id)
            ->set('selectedRoleId', $moderatorRole->id)
            ->call('assignRole');

        expect(Cache::has(sprintf('user_%d_role_name', $targetUser->id)))->toBeFalse();
    });

    it('can change role from one to another', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $adminRole = UserRole::query()->where('name', 'Staff')->first();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showAssignRoleModal', $moderator->id)
            ->set('selectedRoleId', $adminRole->id)
            ->call('assignRole');

        expect($moderator->fresh()->user_role_id)->toBe($adminRole->id);
    });
});

describe('RoleManagement Role Removal', function (): void {
    it('shows remove role confirmation modal', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showRemoveRoleModal', $moderator->id)
            ->assertSet('showRemoveModal', true)
            ->assertSet('userToRemoveRoleId', $moderator->id);
    });

    it('removes role from user successfully', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showRemoveRoleModal', $moderator->id)
            ->call('removeRole')
            ->assertSet('showRemoveModal', false);

        expect($moderator->fresh()->user_role_id)->toBeNull();
    });

    it('prevents admin from removing their own role', function (): void {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showRemoveRoleModal', $admin->id)
            ->call('removeRole');

        expect($admin->fresh()->user_role_id)->not->toBeNull();
    });

    it('clears cache after role removal', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        // Prime the cache
        Cache::put(sprintf('user_%d_role_name', $moderator->id), 'moderator', 3600);

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showRemoveRoleModal', $moderator->id)
            ->call('removeRole');

        expect(Cache::has(sprintf('user_%d_role_name', $moderator->id)))->toBeFalse();
    });
});

describe('RoleManagement Modal Control', function (): void {
    it('closes assign modal and resets state', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showAssignRoleModal', $targetUser->id)
            ->call('closeAssignModal')
            ->assertSet('showAssignModal', false)
            ->assertSet('selectedUserId', null)
            ->assertSet('selectedRoleId', null);
    });

    it('closes remove modal and resets state', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showRemoveRoleModal', $moderator->id)
            ->call('closeRemoveModal')
            ->assertSet('showRemoveModal', false)
            ->assertSet('userToRemoveRoleId', null);
    });
});

describe('RoleManagement Role Filtering', function (): void {
    it('filters users by role', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $moderatorRole = UserRole::query()->where('name', 'Moderator')->first();

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('roleFilter', $moderatorRole->id);

        $usersWithRoles = $component->get('usersWithRoles');

        // All users in the result should be moderators
        foreach ($usersWithRoles as $user) {
            expect($user->user_role_id)->toBe($moderatorRole->id);
        }
    });

    it('resets pagination when filter changes', function (): void {
        $admin = User::factory()->admin()->create();
        $moderatorRole = UserRole::query()->firstOrCreate(
            ['name' => 'Moderator'],
            ['short_name' => 'Mod', 'description' => 'A moderator', 'color_class' => 'orange', 'icon' => 'wrench']
        );

        Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->set('roleFilter', $moderatorRole->id)
            ->assertSet('paginators', ['page' => 1]);
    });
});

describe('RoleManagement Computed Properties', function (): void {
    it('returns paginated users with roles', function (): void {
        $admin = User::factory()->admin()->create();
        User::factory()->moderator()->count(3)->create();

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.role-management');

        $usersWithRoles = $component->get('usersWithRoles');

        // Should include the admin and the 3 moderators
        expect($usersWithRoles->total())->toBe(4);
    });

    it('returns all available roles', function (): void {
        $admin = User::factory()->admin()->create();
        // Ensure both roles exist by creating a moderator
        User::factory()->moderator()->create();

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.role-management');

        $roles = $component->get('roles');

        expect($roles->count())->toBeGreaterThanOrEqual(2);
        expect($roles->pluck('name')->toArray())->toContain('Staff', 'Moderator');
    });

    it('returns selected user property when user is selected', function (): void {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showAssignRoleModal', $targetUser->id);

        expect($component->get('selectedUser')->id)->toBe($targetUser->id);
    });

    it('returns user to remove role property when user is selected', function (): void {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        $component = Livewire::actingAs($admin)
            ->test('pages::admin.role-management')
            ->call('showRemoveRoleModal', $moderator->id);

        expect($component->get('userToRemoveRole')->id)->toBe($moderator->id);
    });
});
