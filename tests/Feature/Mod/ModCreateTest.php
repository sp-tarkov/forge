<?php

declare(strict_types=1);

use App\Livewire\Page\Mod\Create;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

describe('Mod Create Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Authentication and Authorization', function (): void {
        it('verifies MFA-enabled user has MFA enabled', function (): void {
            $user = User::factory()->withMfa()->create();
            expect($user->hasMfaEnabled())->toBeTrue();
        });

        it('verifies MFA-enabled user can create mods', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);
            expect($user->can('create', Mod::class))->toBeTrue();
        });
    });

    describe('Component Rendering', function (): void {
        it('renders the Livewire Create component without error', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test(Create::class)
                ->assertStatus(200);
        });
    });

    describe('Form Validation', function (): void {
        it('requires all required fields to create a mod', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->call('save')
                ->assertHasErrors(['name', 'guid', 'teaser', 'description', 'license']);
        });

        it('validates GUID format', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
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
        it('prevents creating a mod with duplicate GUID', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();

            // Create a mod with a specific GUID
            $existingMod = Mod::factory()->create(['guid' => 'com.example.existing']);

            $this->actingAs($user);

            // Attempt to create a new mod with the same GUID
            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'New Mod Name')
                ->set('guid', $existingMod->guid)
                ->set('teaser', 'New teaser')
                ->set('description', 'New description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeUrl', 'https://github.com/example/new')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['guid']);
        });

        it('allows creating a mod with unique GUID', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();

            // Create a mod with a specific GUID to ensure uniqueness check
            Mod::factory()->create(['guid' => 'com.example.existing']);

            $this->actingAs($user);

            // Create a new mod with a unique GUID
            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'New Mod Name')
                ->set('guid', 'com.example.unique')
                ->set('teaser', 'New teaser')
                ->set('description', 'New description')
                ->set('license', (string) $license->id)
                ->set('sourceCodeUrl', 'https://github.com/example/new')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();
        });
    });
});
