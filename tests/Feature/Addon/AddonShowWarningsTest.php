<?php

declare(strict_types=1);

use App\Livewire\Page\Addon\Show;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\SptVersion;
use App\Models\User;
use Livewire\Livewire;

describe('Addon Show Page Warnings', function (): void {
    beforeEach(function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('does not show no published versions warning when addon has a published version in the past', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null, // Addon is unpublished
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subHour(), // Version published in the past
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->not->toHaveKey('no_published_versions')
            ->and($warnings)->toHaveKey('unpublished'); // But should show unpublished warning
    });

    it('shows no published versions warning when addon has a version scheduled for future', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->addDay(), // Version scheduled for future
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_published_versions');
    });

    it('shows no published versions warning when addon has only unpublished versions', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => null, // Version not published
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_published_versions');
    });

    it('shows unpublished warning when addon is not published', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null, // Addon not published
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('unpublished');
    });

    it('does not show unpublished warning when addon is published', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(), // Addon is published
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->not->toHaveKey('unpublished');
    });

    it('shows disabled warning when addon is disabled', function (): void {
        // User must be a moderator to view disabled addons
        $moderator = User::factory()->moderator()->create();
        $this->actingAs($moderator);

        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($moderator, 'owner')
            ->create([
                'disabled' => true, // Addon is disabled
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        // Create component instance directly to avoid Livewire lifecycle issues
        $component = new Show();
        $component->addon = $addon;

        $warnings = $component->getWarningMessages();

        expect($warnings)->toHaveKey('disabled');
    });

    it('shows no enabled versions warning when all versions are disabled', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => true, // Version is disabled
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_enabled_versions');
    });

    it('shows no versions warning when addon has no versions at all', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        // No versions created

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        $warnings = $component->instance()->getWarningMessages();

        expect($warnings)->toHaveKey('no_versions');
    });

    it('does not show warnings to guests', function (): void {
        auth()->logout();

        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for(User::factory()->create(), 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null, // Unpublished
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        // This will fail authorization, so we expect a 403
        $this->get(route('addon.show', [$addon->id, $addon->slug]))
            ->assertForbidden();
    });

    it('shows warnings to addon owner', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        expect($component->instance()->shouldShowWarnings())->toBeTrue()
            ->and($component->instance()->getWarningMessages())->toHaveKey('unpublished');
    });

    it('shows warnings to addon author', function (): void {
        $owner = User::factory()->create();

        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);

        $addon = Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->hasAttached($this->user, [], 'additionalAuthors')
            ->create([
                'disabled' => false,
                'published_at' => null,
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        expect($component->instance()->shouldShowWarnings())->toBeTrue()
            ->and($component->instance()->getWarningMessages())->toHaveKey('unpublished');
    });

    it('shows parent mod warning when parent mod has no published versions', function (): void {
        $mod = Mod::factory()->create([
            'disabled' => false,
            'published_at' => now()->subDay(),
        ]);
        // No mod versions created

        $addon = Addon::factory()
            ->for($mod)
            ->for($this->user, 'owner')
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        AddonVersion::factory()
            ->for($addon)
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $component = Livewire::test(Show::class, ['addonId' => $addon->id, 'slug' => $addon->slug]);

        expect($component->instance()->shouldShowWarnings())->toBeTrue()
            ->and($component->instance()->getWarningMessages())->toHaveKey('parent_mod_not_visible');
    });
});
