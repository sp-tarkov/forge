<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ModVersionsDisabledNotification;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('shows each disabled version with its reason and edit link in the dashboard', function (): void {
    $user = User::factory()->create();

    $versions = [[
        'mod_name' => 'SAIN',
        'version' => '4.4.1-FikaEnhanced',
        'url' => 'https://forge.test/mod/791/version/13340/edit',
        'reason' => 'The "-FikaEnhanced" label is valid SemVer but cannot be used for dependency matching.',
    ]];

    $user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => ModVersionsDisabledNotification::class,
        'data' => new ModVersionsDisabledNotification($versions)->toArray($user),
        'read_at' => null,
    ]);

    Livewire::actingAs($user)->test('notification-center')
        ->assertSee('SAIN 4.4.1-FikaEnhanced')
        ->assertSee('cannot be used for dependency matching')
        ->assertSee('https://forge.test/mod/791/version/13340/edit')
        ->assertSee('version unpublished');
});

it('skips the broadcast refresh when notifications are unchanged', function (): void {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('notification-center')
        ->assertSet('unreadCount', 0);

    DB::table('notifications')->insert([
        'id' => fake()->uuid(),
        'type' => ModVersionsDisabledNotification::class,
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['versions' => []]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $component->call('refreshNotifications')
        ->assertSet('unreadCount', 0);
});

it('refreshes from a broadcast ping when notifications change', function (): void {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('notification-center')
        ->assertSet('unreadCount', 0);

    $user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => ModVersionsDisabledNotification::class,
        'data' => ['versions' => []],
        'read_at' => null,
    ]);

    $component->call('refreshNotifications')
        ->assertSet('unreadCount', 1);
});

it('listens on the user private broadcast channel', function (): void {
    $user = User::factory()->create();

    $listeners = Livewire::actingAs($user)->test('notification-center')
        ->instance()
        ->getListeners();

    expect($listeners)->toBe([
        sprintf('echo-private:user.%d,UserNotificationsChanged', $user->id) => 'refreshNotifications',
    ]);
});
