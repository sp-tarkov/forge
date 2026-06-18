<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\License;
use App\Models\Mod;
use App\Models\SourceCodeLink;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('honeypot.enabled', false);
});

describe('custom AI disclosure', function (): void {
    beforeEach(function (): void {
        $this->license = License::factory()->create();
        $this->mod = Mod::factory()->create();
    });

    it('hydrates the custom AI disclosure field from the existing addon', function (): void {
        $owner = User::factory()->withMfa()->create();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => 'Existing addon disclosure.',
                'license_id' => $this->license->id,
            ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->assertSet('customAiDisclosure', 'Existing addon disclosure.');
    });

    it('hydrates an empty string when the addon has no custom AI disclosure', function (): void {
        $owner = User::factory()->withMfa()->create();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => null,
                'license_id' => $this->license->id,
            ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->assertSet('customAiDisclosure', '');
    });

    it('persists the custom AI disclosure when AI content is enabled and a message is provided', function (): void {
        $owner = User::factory()->withMfa()->create();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => null,
                'license_id' => $this->license->id,
            ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', 'Used AI to refactor a helper class.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->custom_ai_disclosure)->toBe('Used AI to refactor a helper class.');
    });

    it('clears the custom AI disclosure when AI content is disabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => 'Old disclosure that should be cleared.',
                'license_id' => $this->license->id,
            ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContent', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeFalse();
        expect($addon->custom_ai_disclosure)->toBeNull();
    });

    it('clears the custom AI disclosure when the message is emptied while AI content remains enabled', function (): void {
        $owner = User::factory()->withMfa()->create();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => 'Will be cleared.',
                'license_id' => $this->license->id,
            ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', '')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->custom_ai_disclosure)->toBeNull();
    });

    it('rejects a custom AI disclosure longer than 1000 characters', function (): void {
        $owner = User::factory()->withMfa()->create();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create([
                'contains_ai_content' => true,
                'license_id' => $this->license->id,
            ]));

        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('customAiDisclosure', str_repeat('a', 1001))
            ->call('save')
            ->assertHasErrors(['customAiDisclosure']);
    });
});

describe('publication date', function (): void {
    it('allows clearing the published_at date when editing an addon', function (): void {
        $license = License::factory()->create();
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::withoutEvents(fn (): Addon => Addon::factory()->create([
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
