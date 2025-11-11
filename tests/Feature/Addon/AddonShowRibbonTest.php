<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Roles will be created automatically by the factory when needed

    $this->user = User::factory()->moderator()->create();
    $this->mod = Mod::factory()->create();
});

describe('Addon Show Page Ribbon Display', function (): void {
    it('shows disabled ribbon when addon is disabled to moderator', function (): void {
        $moderator = User::factory()->moderator()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->disabled()
            ->create();

        $response = $this->actingAs($moderator)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Disabled');
    });

    it('shows unpublished ribbon when addon is unpublished to moderator', function (): void {
        $moderator = User::factory()->moderator()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->create(['published_at' => null]);

        $response = $this->actingAs($moderator)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Unpublished');
    });

    it('shows scheduled ribbon when addon is scheduled for future', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->create(['published_at' => now()->addWeek()]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertSee('Scheduled');
    });

    it('does not show ribbon when addon is published and enabled', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create();

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertDontSee('ribbon red')
            ->assertDontSee('ribbon amber')
            ->assertDontSee('ribbon emerald');
    });

    it('disabled ribbon has priority over unpublished for moderator', function (): void {
        $moderator = User::factory()->moderator()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->disabled()
            ->create(['published_at' => null]);

        $response = $this->actingAs($moderator)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Disabled')
            ->assertDontSee('Unpublished');
    });

    it('shows ribbon to addon owner even if unpublished', function (): void {
        $owner = User::factory()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create(['published_at' => null]);

        $response = $this->actingAs($owner)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertSee('Unpublished');
    });

    it('shows ribbon to addon author even if unpublished', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->for($owner, 'owner')
            ->create(['published_at' => null]);

        $addon->authors()->attach($author);

        $response = $this->actingAs($author)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSeeLivewire('ribbon.addon')
            ->assertSee('Unpublished');
    });

    it('shows disabled ribbon to admin', function (): void {
        $admin = User::factory()->admin()->create();
        $addon = Addon::factory()
            ->for($this->mod)
            ->disabled()
            ->create();

        $response = $this->actingAs($admin)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSee('Disabled');
    });
});
