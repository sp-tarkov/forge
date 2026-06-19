<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('creation flow pages', function (): void {
    it('renders the addon guidelines page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();

        $this->actingAs($user)
            ->get(route('addon.guidelines', $mod->id))
            ->assertOk();
    });

    it('renders the addon path-check page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();

        $this->actingAs($user)
            ->get(route('addon.path-check', $mod->id))
            ->assertOk()
            ->assertSeeText('Choose the Right Path')
            ->assertSeeText('An add-on fits')
            ->assertSeeText('This should be its own mod');
    });

    it('routes users from guidelines to path-check after acknowledgment', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();

        $this->actingAs($user);

        Livewire::test('pages::addon.guidelines-acknowledgment', ['mod' => $mod])
            ->call('agree')
            ->assertRedirect(route('addon.path-check', ['mod' => $mod->id]));
    });

    it('routes users from path-check to addon create on proceed', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();

        $this->actingAs($user);

        Livewire::test('pages::addon.path-check', ['mod' => $mod])
            ->call('proceed')
            ->assertRedirect(route('addon.create', ['mod' => $mod->id]));
    });

    it('blocks unauthorized users from the addon path-check page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create(['addons_disabled' => true]);

        $this->actingAs($user)
            ->get(route('addon.path-check', $mod->id))
            ->assertForbidden();
    });

    it('renders the addon version create page', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->addonsEnabled()->create();
        $addon = Addon::factory()->published()->recycle($mod)->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('addon.version.create', $addon->id))
            ->assertOk();
    });
});

describe('authorization', function (): void {
    it('requires an MFA-enabled user', function (): void {
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

describe('component rendering', function (): void {
    it('can mount and render the create component', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->for($user, 'owner')->create();
        $this->actingAs($user);

        Livewire::test('pages::addon.create', ['mod' => $mod])
            ->assertOk();
    });
});

describe('custom AI disclosure', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->withMfa()->create();
        $this->license = License::factory()->create();
        $this->mod = Mod::factory()->addonsEnabled()->create();
        $this->actingAs($this->user);
    });

    it('persists the custom AI disclosure when AI content is enabled and a message is provided', function (): void {
        Livewire::test('pages::addon.create', ['mod' => $this->mod])
            ->set('honeypotData.nameFieldName', 'name')
            ->set('honeypotData.validFromFieldName', 'valid_from')
            ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
            ->set('name', 'AI Disclosure Addon')
            ->set('teaser', 'Test teaser')
            ->set('description', 'Test description')
            ->set('license', (string) $this->license->id)
            ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', 'Used AI to draft documentation.')
            ->set('containsAds', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addon = Addon::query()->where('name', 'AI Disclosure Addon')->first();
        expect($addon)->not->toBeNull();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->custom_ai_disclosure)->toBe('Used AI to draft documentation.');
    });

    it('requires a disclosure message when AI content is enabled', function (): void {
        Livewire::test('pages::addon.create', ['mod' => $this->mod])
            ->set('honeypotData.nameFieldName', 'name')
            ->set('honeypotData.validFromFieldName', 'valid_from')
            ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
            ->set('name', 'AI No Message Addon')
            ->set('teaser', 'Test teaser')
            ->set('description', 'Test description')
            ->set('license', (string) $this->license->id)
            ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', '')
            ->set('containsAds', false)
            ->call('save')
            ->assertHasErrors(['customAiDisclosure' => 'required_if']);

        expect(Addon::query()->where('name', 'AI No Message Addon')->exists())->toBeFalse();
    });

    it('persists null when AI content is disabled even if a disclosure message is provided', function (): void {
        Livewire::test('pages::addon.create', ['mod' => $this->mod])
            ->set('honeypotData.nameFieldName', 'name')
            ->set('honeypotData.validFromFieldName', 'valid_from')
            ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
            ->set('name', 'No AI Addon')
            ->set('teaser', 'Test teaser')
            ->set('description', 'Test description')
            ->set('license', (string) $this->license->id)
            ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('containsAiContent', false)
            ->set('customAiDisclosure', 'Some leftover text the user typed before unchecking.')
            ->set('containsAds', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addon = Addon::query()->where('name', 'No AI Addon')->first();
        expect($addon)->not->toBeNull();
        expect($addon->contains_ai_content)->toBeFalse();
        expect($addon->custom_ai_disclosure)->toBeNull();
    });

    it('rejects a custom AI disclosure longer than 1000 characters', function (): void {
        Livewire::test('pages::addon.create', ['mod' => $this->mod])
            ->set('honeypotData.nameFieldName', 'name')
            ->set('honeypotData.validFromFieldName', 'valid_from')
            ->set('honeypotData.encryptedValidFrom', encrypt(now()->timestamp))
            ->set('name', 'Too Long Addon')
            ->set('teaser', 'Test teaser')
            ->set('description', 'Test description')
            ->set('license', (string) $this->license->id)
            ->set('sourceCodeLinks.0.url', 'https://github.com/test/repo')
            ->set('sourceCodeLinks.0.label', '')
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', str_repeat('a', 1001))
            ->call('save')
            ->assertHasErrors(['customAiDisclosure']);
    });
});
