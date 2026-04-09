<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\SourceCodeLink;
use App\Models\User;
use Livewire\Livewire;

describe('Addon Edit Form', function (): void {

    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);
    });

    it('allows clearing the published_at date when editing an addon', function (): void {
        $license = License::factory()->create();
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::withoutEvents(fn () => Addon::factory()->create([
            'mod_id' => $mod->id,
            'owner_id' => $user->id,
            'published_at' => now(),
            'license_id' => $license->id,
        ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($user);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->assertNotSet('publishedAt', null)
            ->set('publishedAt', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addon->refresh();
        expect($addon->published_at)->toBeNull();
    });
});
