<?php

declare(strict_types=1);

use App\Livewire\User\BanAction;
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
        ->test(BanAction::class, ['user' => $user]);

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
        ->test(BanAction::class, ['user' => $user]);

    expect($admin->can('ban', $user))->toBeTrue();
    expect($user->isBanned())->toBeTrue();
    $component->assertSee('Unban User');
});

it('does not show ban buttons for moderators', function (): void {
    $moderator = User::factory()->create();
    $moderator->assignRole(UserRole::factory()->create(['name' => 'Moderator']));

    $user = User::factory()->create();

    $component = Livewire::actingAs($moderator)
        ->test(BanAction::class, ['user' => $user]);

    expect($moderator->can('ban', $user))->toBeFalse();
});

it('does not show ban buttons for regular users', function (): void {
    $regularUser = User::factory()->create();
    $targetUser = User::factory()->create();

    $component = Livewire::actingAs($regularUser)
        ->test(BanAction::class, ['user' => $targetUser]);

    expect($regularUser->can('ban', $targetUser))->toBeFalse();
});

it('does not allow admin to ban other admin', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);

    $admin1 = User::factory()->create();
    $admin1->assignRole($adminRole);

    $admin2 = User::factory()->create();
    $admin2->assignRole($adminRole);

    $component = Livewire::actingAs($admin1)
        ->test(BanAction::class, ['user' => $admin2]);

    expect($admin1->can('ban', $admin2))->toBeFalse();
});

it('does not allow admin to ban themselves', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $component = Livewire::actingAs($admin)
        ->test(BanAction::class, ['user' => $admin]);

    expect($admin->can('ban', $admin))->toBeFalse();
});

it('allows admin to ban user with duration', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(BanAction::class, ['user' => $user])
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
        ->test(BanAction::class, ['user' => $user])
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
        ->test(BanAction::class, ['user' => $user])
        ->call('unban');

    expect($user->fresh()->isBanned())->toBeFalse();
});

it('opens ban modal when clicking ban button', function (): void {
    $adminRole = UserRole::factory()->create(['name' => 'Staff']);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $user = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(BanAction::class, ['user' => $user])
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
        ->test(BanAction::class, ['user' => $user])
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
        ->test(BanAction::class, ['user' => $user])
        ->call('ban')
        ->assertHasErrors(['duration']);
});

it('provides correct duration options', function (): void {
    $admin = User::factory()->create();
    $user = User::factory()->create();

    $component = Livewire::actingAs($admin)
        ->test(BanAction::class, ['user' => $user]);

    $expectedOptions = [
        '1_hour' => '1 Hour',
        '24_hours' => '24 Hours',
        '7_days' => '7 Days',
        '30_days' => '30 Days',
        'permanent' => 'Permanent',
    ];

    expect($component->instance()->getDurationOptions())->toBe($expectedOptions);
});
