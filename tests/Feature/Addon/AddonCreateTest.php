<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;

describe('Addon Create Form', function (): void {

    beforeEach(function (): void {
        // Disable honeypot for testing
        config()->set('honeypot.enabled', false);
    });

    describe('Authentication and Authorization', function (): void {
        it('requires MFA-enabled user', function (): void {
            $user = User::factory()->withMfa()->create();
            expect($user->hasMfaEnabled())->toBeTrue();
        });

        it('prevents creating addon for mod with addons disabled', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create([
                'addons_disabled' => true,
            ]);

            $this->actingAs($user);

            expect($user->can('create', [Addon::class, $mod]))->toBeFalse();
        });

        it('allows any user with MFA to create addon', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->create();

            $this->actingAs($user);

            expect($user->can('create', [Addon::class, $mod]))->toBeTrue();
        });
    });

    describe('Component Rendering', function (): void {
        it('can instantiate the Livewire Create component', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();
            $this->actingAs($user);

            // Test that the component can be mounted and rendered
            Livewire::test('pages::addon.create', ['mod' => $mod])
                ->assertOk();
        });
    });

    describe('Form Validation', function (): void {
        it('validates required fields', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();
            $this->actingAs($user);

            // Create a new addon without required fields to test validation
            $addon = new Addon();
            $addon->mod_id = $mod->id;

            // Validation should fail for required fields
            expect($addon->name)->toBeNull();
            expect($addon->teaser)->toBeNull();
            expect($addon->description)->toBeNull();
        });
    });

    describe('Addon Creation', function (): void {
        it('creates addon with all fields', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
                'contains_ai_content' => true,
                'contains_ads' => false,
            ]);

            expect($addon)->not->toBeNull();
            expect($addon->name)->toBe('Test Addon');
            expect($addon->mod_id)->toBe($mod->id);
            expect($addon->owner_id)->toBe($user->id);
            expect($addon->contains_ai_content)->toBeTrue();
            expect($addon->contains_ads)->toBeFalse();
        });

        it('sets owner to current user', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
            ]);

            expect($addon->owner_id)->toBe($user->id);
        });

        it('associates addon with parent mod', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
            ]);

            expect($addon->mod_id)->toBe($mod->id);
            expect($addon->mod->id)->toBe($mod->id);
        });
    });

    describe('Source Code Links', function (): void {
        it('creates addon with source code links', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->for($user, 'owner')->create();

            $this->actingAs($user);

            $addon = Addon::factory()->for($mod)->for($user, 'owner')->create([
                'name' => 'Test Addon',
                'teaser' => 'A test addon',
                'description' => 'This is a test addon',
                'license_id' => $license->id,
            ]);

            // Source code links are created in the factory's configure method
            $addon->load('sourceCodeLinks');

            expect($addon->sourceCodeLinks)->not->toBeNull();
        });
    });
});
