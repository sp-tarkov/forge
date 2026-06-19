<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ModVersionsDisabledNotification;
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
