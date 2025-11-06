<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

describe('PublishedScope', function (): void {
    beforeEach(function (): void {
        $this->owner = User::factory()->create();
        $this->author = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->admin = User::factory()->admin()->create();
    });

    describe('Mod model', function (): void {
        it('filters out unpublished mods for guests', function (): void {
            $publishedMod = Mod::factory()->create([
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
            ]);
            $futureMod = Mod::factory()->create([
                'published_at' => Date::now()->addDay(),
            ]);

            $results = Mod::query()->get();

            expect($results->pluck('id'))->toContain($publishedMod->id);
            expect($results->pluck('id'))->not->toContain($unpublishedMod->id);
            expect($results->pluck('id'))->not->toContain($futureMod->id);
        });

        it('allows admins to see all mods', function (): void {
            $publishedMod = Mod::factory()->create([
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedMod = Mod::factory()->create([
                'published_at' => null,
            ]);
            $futureMod = Mod::factory()->create([
                'published_at' => Date::now()->addDay(),
            ]);

            $this->actingAs($this->admin);
            $results = Mod::query()->get();

            expect($results->pluck('id'))->toContain($publishedMod->id);
            expect($results->pluck('id'))->toContain($unpublishedMod->id);
            expect($results->pluck('id'))->toContain($futureMod->id);
        });

        it('allows owners to see their own unpublished mods', function (): void {
            $publishedMod = Mod::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedMod = Mod::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => null,
            ]);
            $futureMod = Mod::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => Date::now()->addDay(),
            ]);
            $otherUserMod = Mod::factory()->create([
                'owner_id' => $this->otherUser->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->owner);
            $results = Mod::query()->get();

            expect($results->pluck('id'))->toContain($publishedMod->id);
            expect($results->pluck('id'))->toContain($unpublishedMod->id);
            expect($results->pluck('id'))->toContain($futureMod->id);
            expect($results->pluck('id'))->not->toContain($otherUserMod->id);
        });

        it('allows authors to see mods they authored', function (): void {
            $mod = Mod::factory()->create([
                'published_at' => null,
            ]);
            $mod->authors()->attach($this->author->id);

            $this->actingAs($this->author);
            $results = Mod::query()->get();

            expect($results->pluck('id'))->toContain($mod->id);
        });
    });

    describe('ModVersion model', function (): void {
        it('filters out unpublished versions for guests', function (): void {
            $mod = Mod::factory()->create(['published_at' => Date::now()->subDay()]);
            $publishedVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => null,
            ]);
            $futureVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->addDay(),
            ]);

            $results = ModVersion::query()->get();

            expect($results->pluck('id'))->toContain($publishedVersion->id);
            expect($results->pluck('id'))->not->toContain($unpublishedVersion->id);
            expect($results->pluck('id'))->not->toContain($futureVersion->id);
        });

        it('allows admins to see all versions', function (): void {
            $mod = Mod::factory()->create(['published_at' => Date::now()->subDay()]);
            $publishedVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->admin);
            $results = ModVersion::query()->get();

            expect($results->pluck('id'))->toContain($publishedVersion->id);
            expect($results->pluck('id'))->toContain($unpublishedVersion->id);
        });

        it('allows mod owners to see their own unpublished versions', function (): void {
            $mod = Mod::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $publishedVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => null,
            ]);
            $otherMod = Mod::factory()->create([
                'owner_id' => $this->otherUser->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $otherVersion = ModVersion::factory()->create([
                'mod_id' => $otherMod->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->owner);
            $results = ModVersion::query()->get();

            expect($results->pluck('id'))->toContain($publishedVersion->id);
            expect($results->pluck('id'))->toContain($unpublishedVersion->id);
            expect($results->pluck('id'))->not->toContain($otherVersion->id);
        });

        it('allows mod authors to see versions of mods they authored', function (): void {
            $mod = Mod::factory()->create(['published_at' => Date::now()->subDay()]);
            $mod->authors()->attach($this->author->id);
            $unpublishedVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->author);
            $results = ModVersion::query()->get();

            expect($results->pluck('id'))->toContain($unpublishedVersion->id);
        });
    });

    describe('Addon model', function (): void {
        it('filters out unpublished addons for guests', function (): void {
            $publishedAddon = Addon::factory()->create([
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedAddon = Addon::factory()->create([
                'published_at' => null,
            ]);
            $futureAddon = Addon::factory()->create([
                'published_at' => Date::now()->addDay(),
            ]);

            $results = Addon::query()->get();

            expect($results->pluck('id'))->toContain($publishedAddon->id);
            expect($results->pluck('id'))->not->toContain($unpublishedAddon->id);
            expect($results->pluck('id'))->not->toContain($futureAddon->id);
        });

        it('allows admins to see all addons', function (): void {
            $publishedAddon = Addon::factory()->create([
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedAddon = Addon::factory()->create([
                'published_at' => null,
            ]);
            $futureAddon = Addon::factory()->create([
                'published_at' => Date::now()->addDay(),
            ]);

            $this->actingAs($this->admin);
            $results = Addon::query()->get();

            expect($results->pluck('id'))->toContain($publishedAddon->id);
            expect($results->pluck('id'))->toContain($unpublishedAddon->id);
            expect($results->pluck('id'))->toContain($futureAddon->id);
        });

        it('allows owners to see their own unpublished addons', function (): void {
            $publishedAddon = Addon::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedAddon = Addon::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => null,
            ]);
            $futureAddon = Addon::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => Date::now()->addDay(),
            ]);
            $otherUserAddon = Addon::factory()->create([
                'owner_id' => $this->otherUser->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->owner);
            $results = Addon::query()->get();

            expect($results->pluck('id'))->toContain($publishedAddon->id);
            expect($results->pluck('id'))->toContain($unpublishedAddon->id);
            expect($results->pluck('id'))->toContain($futureAddon->id);
            expect($results->pluck('id'))->not->toContain($otherUserAddon->id);
        });

        it('allows authors to see addons they authored', function (): void {
            $addon = Addon::factory()->create([
                'published_at' => null,
            ]);
            $addon->authors()->attach($this->author->id);

            $this->actingAs($this->author);
            $results = Addon::query()->get();

            expect($results->pluck('id'))->toContain($addon->id);
        });
    });

    describe('AddonVersion model', function (): void {
        it('filters out unpublished versions for guests', function (): void {
            $addon = Addon::factory()->create(['published_at' => Date::now()->subDay()]);
            $publishedVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => null,
            ]);
            $futureVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => Date::now()->addDay(),
            ]);

            $results = AddonVersion::query()->get();

            expect($results->pluck('id'))->toContain($publishedVersion->id);
            expect($results->pluck('id'))->not->toContain($unpublishedVersion->id);
            expect($results->pluck('id'))->not->toContain($futureVersion->id);
        });

        it('allows admins to see all versions', function (): void {
            $addon = Addon::factory()->create(['published_at' => Date::now()->subDay()]);
            $publishedVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->admin);
            $results = AddonVersion::query()->get();

            expect($results->pluck('id'))->toContain($publishedVersion->id);
            expect($results->pluck('id'))->toContain($unpublishedVersion->id);
        });

        it('allows addon owners to see their own unpublished versions', function (): void {
            $addon = Addon::factory()->create([
                'owner_id' => $this->owner->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $publishedVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => null,
            ]);
            $otherAddon = Addon::factory()->create([
                'owner_id' => $this->otherUser->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $otherVersion = AddonVersion::factory()->create([
                'addon_id' => $otherAddon->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->owner);
            $results = AddonVersion::query()->get();

            expect($results->pluck('id'))->toContain($publishedVersion->id);
            expect($results->pluck('id'))->toContain($unpublishedVersion->id);
            expect($results->pluck('id'))->not->toContain($otherVersion->id);
        });

        it('allows addon authors to see versions of addons they authored', function (): void {
            $addon = Addon::factory()->create(['published_at' => Date::now()->subDay()]);
            $addon->authors()->attach($this->author->id);
            $unpublishedVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => null,
            ]);

            $this->actingAs($this->author);
            $results = AddonVersion::query()->get();

            expect($results->pluck('id'))->toContain($unpublishedVersion->id);
        });
    });

    describe('Consistency between Mod and Addon', function (): void {
        it('applies the same filtering logic to mods and addons for guests', function (): void {
            $publishedMod = Mod::factory()->create(['published_at' => Date::now()->subDay()]);
            $unpublishedMod = Mod::factory()->create(['published_at' => null]);
            $publishedAddon = Addon::factory()->create(['published_at' => Date::now()->subDay()]);
            $unpublishedAddon = Addon::factory()->create(['published_at' => null]);

            $modResults = Mod::query()->get();
            $addonResults = Addon::query()->get();

            expect($modResults->pluck('id'))->toContain($publishedMod->id);
            expect($modResults->pluck('id'))->not->toContain($unpublishedMod->id);
            expect($addonResults->pluck('id'))->toContain($publishedAddon->id);
            expect($addonResults->pluck('id'))->not->toContain($unpublishedAddon->id);
        });

        it('applies the same filtering logic to mod versions and addon versions for guests', function (): void {
            $mod = Mod::factory()->create(['published_at' => Date::now()->subDay()]);
            $publishedModVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedModVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => null,
            ]);

            $addon = Addon::factory()->create(['published_at' => Date::now()->subDay()]);
            $publishedAddonVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => Date::now()->subDay(),
            ]);
            $unpublishedAddonVersion = AddonVersion::factory()->create([
                'addon_id' => $addon->id,
                'published_at' => null,
            ]);

            $modVersionResults = ModVersion::query()->get();
            $addonVersionResults = AddonVersion::query()->get();

            expect($modVersionResults->pluck('id'))->toContain($publishedModVersion->id);
            expect($modVersionResults->pluck('id'))->not->toContain($unpublishedModVersion->id);
            expect($addonVersionResults->pluck('id'))->toContain($publishedAddonVersion->id);
            expect($addonVersionResults->pluck('id'))->not->toContain($unpublishedAddonVersion->id);
        });
    });
});
