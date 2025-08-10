<?php

declare(strict_types=1);

use App\Livewire\Page\Mod\Edit;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

describe('Mod Edit Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Form Validation', function (): void {
        it('requires all required fields to update a mod', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->recycle($user)->create();
            $this->actingAs($user);

            Livewire::test(Edit::class, ['modId' => $mod->id])
                ->set('name', '')
                ->set('guid', '')
                ->set('teaser', '')
                ->set('description', '')
                ->set('license', '')
                ->call('save')
                ->assertHasErrors(['name', 'guid', 'teaser', 'description', 'license']);
        });

        it('validates GUID format when editing', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->recycle($user)->create();
            $this->actingAs($user);

            Livewire::test(Edit::class, ['modId' => $mod->id])
                ->set('name', 'Test Mod')
                ->set('guid', 'invalid-guid')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $license->id)
                ->call('save')
                ->assertHasErrors(['guid']);
        });
    });

    describe('GUID Validation', function (): void {
        it('prevents editing mod to use duplicate GUID', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();

            // Create two mods with different GUIDs
            $existingMod = Mod::factory()->create(['guid' => 'com.example.existingmod']);
            $modToEdit = Mod::factory()->recycle($user)->create(['guid' => 'com.example.modtoedit']);

            $this->actingAs($user);

            // Attempt to edit the second mod to use the first mod's GUID
            Livewire::test(Edit::class, ['modId' => $modToEdit->id])
                ->set('name', 'Updated Mod')
                ->set('guid', $existingMod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeUrl', 'https://github.com/example/updated')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['guid']);
        });

        it('allows editing mod to keep its own GUID', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->create();

            // Create a mod
            $mod = Mod::factory()->recycle($user)->create(['guid' => 'com.example.mymod']);

            $this->actingAs($user);

            // Edit the mod keeping the same GUID
            Livewire::test(Edit::class, ['modId' => $mod->id])
                ->set('name', 'Updated Mod Name')
                ->set('guid', $mod->guid)
                ->set('teaser', 'Updated teaser')
                ->set('description', 'Updated description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeUrl', 'https://github.com/example/updated')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();
        });
    });
});