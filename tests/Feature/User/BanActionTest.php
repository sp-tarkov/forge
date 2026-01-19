<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows ban button for admin viewing regular user', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user]);

    expect($admin->can('ban', $user))->toBeTrue();
    expect($user->isBanned())->toBeFalse();
    $component->assertSee('Ban User');
});

it('shows unban button for admin viewing banned user', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();
    $user->ban();

    $component = Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user]);

    expect($admin->can('ban', $user))->toBeTrue();
    expect($user->isBanned())->toBeTrue();
    $component->assertSee('Unban User');
});

it('does not show ban buttons for moderators', function (): void {
    $moderator = User::factory()->moderator()->create();
    $user = User::factory()->create();

    $component = Livewire::actingAs($moderator)
        ->test('user.ban-action', ['user' => $user]);

    expect($moderator->can('ban', $user))->toBeFalse();
});

it('does not show ban buttons for regular users', function (): void {
    $regularUser = User::factory()->create();
    $targetUser = User::factory()->create();

    $component = Livewire::actingAs($regularUser)
        ->test('user.ban-action', ['user' => $targetUser]);

    expect($regularUser->can('ban', $targetUser))->toBeFalse();
});

it('does not allow admin to ban other admin', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);

    $admin1 = User::factory()->create();
    $admin1->assignRole($adminRole);

    $admin2 = User::factory()->create();
    $admin2->assignRole($adminRole);

    $component = Livewire::actingAs($admin1)
        ->test('user.ban-action', ['user' => $admin2]);

    expect($admin1->can('ban', $admin2))->toBeFalse();
});

it('does not allow admin to ban themselves', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $component = Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $admin]);

    expect($admin->can('ban', $admin))->toBeFalse();
});

it('allows admin to ban user with duration', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user])
        ->set('duration', '24_hours')
        ->set('reason', 'Testing ban functionality')
        ->call('ban');

    expect($user->fresh()->isBanned())->toBeTrue();

    $ban = $user->bans()->first();
    expect($ban)->not->toBeNull();
    expect($ban->created_by_id)->toBe($admin->id);
    expect($ban->comment)->toBe('Testing ban functionality');
    expect($ban->expired_at)->not->toBeNull();
});

it('allows admin to ban user permanently', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user])
        ->set('duration', 'permanent')
        ->call('ban');

    expect($user->fresh()->isBanned())->toBeTrue();

    $ban = $user->bans()->first();
    expect($ban)->not->toBeNull();
    expect($ban->expired_at)->toBeNull();
});

it('allows admin to unban user', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();
    $user->ban();

    expect($user->isBanned())->toBeTrue();

    Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user])
        ->call('unban');

    expect($user->fresh()->isBanned())->toBeFalse();
});

it('opens ban modal when clicking ban button', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user])
        ->assertSet('showBanModal', false)
        ->set('showBanModal', true)
        ->assertSet('showBanModal', true);
});

it('opens unban modal when clicking unban button', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();
    $user->ban();

    Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user])
        ->assertSet('showUnbanModal', false)
        ->set('showUnbanModal', true)
        ->assertSet('showUnbanModal', true);
});

it('requires duration selection for ban', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user])
        ->call('ban')
        ->assertHasErrors(['duration']);
});

it('provides correct duration options', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test('user.ban-action', ['user' => $user]);

    $expectedOptions = [
        '1_hour' => '1 Hour',
        '24_hours' => '24 Hours',
        '7_days' => '7 Days',
        '30_days' => '30 Days',
        'permanent' => 'Permanent',
    ];

    expect($component->instance()->getDurationOptions())->toBe($expectedOptions);
});

it('shows ban button for senior moderator viewing regular user', function (): void {
    $seniorMod = User::factory()->seniorModerator()->create();
    $user = User::factory()->create();

    $component = Livewire::actingAs($seniorMod)
        ->test('user.ban-action', ['user' => $user]);

    expect($seniorMod->can('ban', $user))->toBeTrue();
    $component->assertSee('Ban User');
});

it('shows ban button for senior moderator viewing moderator', function (): void {
    $seniorMod = User::factory()->seniorModerator()->create();
    $moderator = User::factory()->moderator()->create();

    expect($seniorMod->can('ban', $moderator))->toBeTrue();
});

it('does not allow senior moderator to ban staff', function (): void {
    $seniorMod = User::factory()->seniorModerator()->create();
    $staff = User::factory()->admin()->create();

    expect($seniorMod->can('ban', $staff))->toBeFalse();
});

it('does not allow senior moderator to ban another senior moderator', function (): void {
    $seniorMod1 = User::factory()->seniorModerator()->create();
    $seniorMod2 = User::factory()->seniorModerator()->create();

    expect($seniorMod1->can('ban', $seniorMod2))->toBeFalse();
});

it('does not allow senior moderator to ban themselves', function (): void {
    $seniorMod = User::factory()->seniorModerator()->create();

    expect($seniorMod->can('ban', $seniorMod))->toBeFalse();
});

it('allows senior moderator to ban and unban user', function (): void {
    $seniorMod = User::factory()->seniorModerator()->create();
    $user = User::factory()->create();

    Livewire::actingAs($seniorMod)
        ->test('user.ban-action', ['user' => $user])
        ->set('duration', '24_hours')
        ->call('ban');

    expect($user->fresh()->isBanned())->toBeTrue();

    Livewire::actingAs($seniorMod)
        ->test('user.ban-action', ['user' => $user->fresh()])
        ->call('unban');

    expect($user->fresh()->isBanned())->toBeFalse();
});

it('allows senior moderator to ban moderator', function (): void {
    $seniorMod = User::factory()->seniorModerator()->create();
    $moderator = User::factory()->moderator()->create();

    Livewire::actingAs($seniorMod)
        ->test('user.ban-action', ['user' => $moderator])
        ->set('duration', '7_days')
        ->call('ban');

    expect($moderator->fresh()->isBanned())->toBeTrue();
});
