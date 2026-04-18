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

    describe('Browser Tests - License', function (): void {
        it('saves license value when changed via the listbox', function (): void {
            $owner = User::factory()->withMfa()->create();
            $originalLicense = License::factory()->create(['name' => 'Original License']);
            $newLicense = License::factory()->create(['name' => 'MIT License']);
            $mod = Mod::factory()->create();

            $addon = Addon::withoutEvents(fn () => Addon::factory()->for($mod)->for($owner, 'owner')->create([
                'license_id' => $originalLicense->id,
            ]));

            SourceCodeLink::factory()->create([
                'sourceable_type' => Addon::class,
                'sourceable_id' => $addon->id,
            ]);

            $this->actingAs($owner);

            $page = visit(route('addon.edit', ['addonId' => $addon->id]));

            $page->assertSee('Original License')
                ->assertNoJavascriptErrors()
                ->click('Original License')
                ->waitForText('MIT License')
                ->click('MIT License')
                ->click('Save Changes')
                ->waitForText($addon->name);

            $addon->refresh();
            expect($addon->license_id)->toBe($newLicense->id);
        });
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
            ->assertNotSet('publishedAtDate', null)
            ->set('publishedAtDate', '')
            ->set('publishedAtTime', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addon->refresh();
        expect($addon->published_at)->toBeNull();
    });
});
