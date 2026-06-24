<?php

declare(strict_types=1);

use App\Models\CommentSubscription;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\User;
use Livewire\Livewire;

it('renders the mod guidelines page', function (): void {
    $this->actingAs(User::factory()->withMfa()->create())
        ->get('/mod/guidelines')
        ->assertOk();
});

it('redirects guests from mod create to login', function (): void {
    $this->get('/mod/create')->assertRedirect('/login');
});

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

            Livewire::test('pages::mod.create')
                ->assertStatus(200);
        });
    });

    describe('Form Validation', function (): void {
        it('requires all required fields to create a mod', function (): void {
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->call('save')
                ->assertHasErrors(['name', 'teaser', 'description', 'license']);
        });

        it('allows submission after removing an added source code link', function (): void {
            $license = License::factory()->create();
            $category = ModCategory::factory()->create();
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Test Mod Remove Link')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $license->id)
                ->set('category', (string) $category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('addSourceCodeLink')
                ->call('removeSourceCodeLink', 1)
                ->call('save')
                ->assertHasNoErrors();
        });

        it('validates GUID format', function (): void {
            $license = License::factory()->create();
            $user = User::factory()->withMfa()->create();
            $this->actingAs($user);

            Livewire::test('pages::mod.create')
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
            Livewire::test('pages::mod.create')
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
            Livewire::test('pages::mod.create')
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

        it('rejects GUIDs containing uppercase letters', function (): void {
            $license = License::factory()->create();
            $category = ModCategory::factory()->create();
            $user = User::factory()->withMfa()->create();

            $this->actingAs($user);

            // GUIDs must be lowercase; an uppercase value fails the pattern.
            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'New Mod Name')
                ->set('guid', 'com.example.UpperCase')
                ->set('teaser', 'New teaser')
                ->set('description', 'New description')
                ->set('license', (string) $license->id)
                ->set('category', (string) $category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/example/new')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['guid']);
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
            Livewire::test('pages::mod.create')
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
            Livewire::test('pages::mod.create')
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
            $component = Livewire::test('pages::mod.create');

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

            Livewire::test('pages::mod.create')
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

            // Verify the mod was created with no GUID (empty input is stored as null)
            $mod = Mod::query()->where('name', 'Test Mod')->first();
            expect($mod)->not->toBeNull();
            expect($mod->guid)->toBeNull();
        });

        it('validates GUID format when provided', function (): void {
            $this->actingAs($this->user);

            Livewire::test('pages::mod.create')
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
            Livewire::test('pages::mod.create')
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

    describe('Custom AI Disclosure', function (): void {
        beforeEach(function (): void {
            $this->user = User::factory()->withMfa()->create();
            $this->license = License::factory()->create();
            $this->category = ModCategory::factory()->create();
            $this->actingAs($this->user);
        });

        it('persists the custom AI disclosure when AI content is enabled and a message is provided', function (): void {
            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'AI Disclosure Mod')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', true)
                ->set('customAiDisclosure', 'Used AI to draft documentation.')
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod = Mod::query()->where('name', 'AI Disclosure Mod')->first();
            expect($mod)->not->toBeNull();
            expect($mod->contains_ai_content)->toBeTrue();
            expect($mod->custom_ai_disclosure)->toBe('Used AI to draft documentation.');
        });

        it('requires a disclosure message when AI content is enabled', function (): void {
            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'AI No Message Mod')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', true)
                ->set('customAiDisclosure', '')
                ->set('containsAds', false)
                ->call('save')
                ->assertHasErrors(['customAiDisclosure' => 'required_if']);

            expect(Mod::query()->where('name', 'AI No Message Mod')->exists())->toBeFalse();
        });

        it('persists null when AI content is disabled even if a disclosure message is provided', function (): void {
            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'No AI Mod')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', false)
                ->set('customAiDisclosure', 'Some leftover text the user typed before unchecking.')
                ->set('containsAds', false)
                ->call('save')
                ->assertHasNoErrors()
                ->assertRedirect();

            $mod = Mod::query()->where('name', 'No AI Mod')->first();
            expect($mod)->not->toBeNull();
            expect($mod->contains_ai_content)->toBeFalse();
            expect($mod->custom_ai_disclosure)->toBeNull();
        });

        it('rejects a custom AI disclosure longer than 1000 characters', function (): void {
            Livewire::test('pages::mod.create')
                ->set('honeypotData.nameFieldName', 'name')
                ->set('honeypotData.validFromFieldName', 'valid_from')
                ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
                ->set('name', 'Too Long Mod')
                ->set('teaser', 'Test teaser')
                ->set('description', 'Test description')
                ->set('license', (string) $this->license->id)
                ->set('category', (string) $this->category->id)
                ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
                ->set('sourceCodeLinks.0.label', '')
                ->set('containsAiContent', true)
                ->set('customAiDisclosure', str_repeat('a', 1001))
                ->call('save')
                ->assertHasErrors(['customAiDisclosure']);
        });
    });
});
