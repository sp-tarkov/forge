<?php

declare(strict_types=1);

use App\Livewire\Page\Mod\Create;
use App\Models\CommentSubscription;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
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
                ->assertHasErrors(['name', 'teaser', 'description', 'license']);
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
                ->set('guid', 'invalid guid!')
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
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/new')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['guid']);
        });

        it('allows creating a mod with unique GUID', function (): void {
            $license = License::factory()->create();
            $category = ModCategory::factory()->create();
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
                ->set('category', (string) $category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/new')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();
        });

        it('treats GUIDs as case-sensitive for uniqueness validation', function (): void {
            $license = License::factory()->create();
            $category = ModCategory::factory()->create();
            $user = User::factory()->withMfa()->create();

            // Create a mod with a specific GUID using lowercase
            Mod::factory()->create(['guid' => 'com.example.casesensitive']);

            $this->actingAs($user);

            // Attempt to create a new mod with a different case GUID - should be allowed
            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'New Mod Name')
                ->set('guid', 'com.example.CaseSensitive')
                ->set('teaser', 'New teaser')
                ->set('description', 'New description')
                ->set('license', (string) $license->id)
                ->set('category', (string) $category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/new')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();
        });
    });

    describe('Subscription', function (): void {
        beforeEach(function (): void {
            $this->user = User::factory()->withMfa()->create();
            $this->actingAs($this->user);

            License::factory()->create(['id' => 1, 'name' => 'MIT']);
            ModCategory::factory()->create(['id' => 1, 'title' => 'Tools']);
        });

        it('subscribes user to comment notifications when checkbox is checked', function (): void {
            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod')
                ->set('guid', 'com.test.mod')
                ->set('teaser', 'A test mod')
                ->set('description', 'This is a test mod')
                ->set('license', 1)
                ->set('category', 1)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/test')
                ->set('sourceCodeLinks.0.label', '')
                ->set('subscribeToComments', true)
                ->call('save');

            $mod = Mod::query()->where('name', 'Test Mod')->first();
            expect($mod)->not->toBeNull();

            // Assert that the user is subscribed
            expect(CommentSubscription::isSubscribed($this->user, $mod))->toBeTrue();
        });

        it('does not subscribe user when checkbox is unchecked', function (): void {
            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod 2')
                ->set('guid', 'com.test.mod2')
                ->set('teaser', 'A test mod')
                ->set('description', 'This is a test mod')
                ->set('license', 1)
                ->set('category', 1)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/test')
                ->set('sourceCodeLinks.0.label', '')
                ->set('subscribeToComments', false)
                ->call('save');

            $mod = Mod::query()->where('name', 'Test Mod 2')->first();
            expect($mod)->not->toBeNull();

            // Assert that the user is NOT subscribed
            expect(CommentSubscription::isSubscribed($this->user, $mod))->toBeFalse();
        });

        it('defaults to subscribing the user', function (): void {
            $component = Livewire::test(Create::class);

            // Assert that the default value is true
            expect($component->instance()->subscribeToComments)->toBeTrue();
        });
    });

    describe('GUID Requirements', function (): void {
        beforeEach(function (): void {
            $this->user = User::factory()->withMfa()->create();
            $this->license = License::factory()->create();
            $this->category = ModCategory::factory()->create();
        });

        it('allows creating a mod without GUID', function (): void {
            $this->actingAs($this->user);

            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod')
                ->set('guid', '') // Empty GUID
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            // Verify the mod was created with empty GUID
            $mod = Mod::query()->where('name', 'Test Mod')->first();
            expect($mod)->not->toBeNull();
            expect($mod->guid)->toBe('');
        });

        it('validates GUID format when provided', function (): void {
            $this->actingAs($this->user);

            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod')
                ->set('guid', 'Invalid GUID Format') // Invalid format
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['guid']);

            // Valid format should work
            Livewire::test(Create::class)
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod 2')
                ->set('guid', 'com.test.mod') // Valid format
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors();
        });
    });
});
