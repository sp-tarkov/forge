<?php

declare(strict_types=1);

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

describe('ModVersion isLegacy method', function (): void {
    it('returns true for empty spt_version_constraint', function (): void {
        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '']);

        expect($modVersion->isLegacy())->toBeTrue();
    });

    it('returns false for set spt_version_constraint', function (): void {
        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '^3.8.0']);

        expect($modVersion->isLegacy())->toBeFalse();
    });
});

describe('ModVersion isLegacyPubliclyVisible method', function (): void {
    it('returns true for legacy version that is published and enabled', function (): void {
        $modVersion = ModVersion::factory()->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        expect($modVersion->isLegacyPubliclyVisible())->toBeTrue();
    });

    it('returns false for legacy version that is unpublished', function (): void {
        $modVersion = ModVersion::factory()->create([
            'spt_version_constraint' => '',
            'published_at' => null,
            'disabled' => false,
        ]);

        expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
    });

    it('returns false for legacy version with future publish date', function (): void {
        $modVersion = ModVersion::factory()->create([
            'spt_version_constraint' => '',
            'published_at' => now()->addDay(),
            'disabled' => false,
        ]);

        expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
    });

    it('returns false for legacy version that is disabled', function (): void {
        $modVersion = ModVersion::factory()->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => true,
        ]);

        expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
    });

    it('returns false for non-legacy version even if published and enabled', function (): void {
        $modVersion = ModVersion::factory()->create([
            'spt_version_constraint' => '^3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        expect($modVersion->isLegacyPubliclyVisible())->toBeFalse();
    });
});

describe('ModVersion legacyPubliclyVisible scope', function (): void {
    it('returns only legacy versions that are published and enabled', function (): void {
        $mod = Mod::factory()->create();

        // Legacy and publicly visible
        $legacyVisible = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Legacy but unpublished
        $legacyUnpublished = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => null,
            'disabled' => false,
        ]);

        // Legacy but disabled
        $legacyDisabled = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => true,
        ]);

        // Non-legacy version
        $nonLegacy = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '^3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        $results = $mod->versions()->legacyPubliclyVisible()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($legacyVisible->id);
    });
});

describe('Mod hasOnlyLegacyVersions method', function (): void {
    beforeEach(function (): void {
        $this->sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
    });

    it('returns true for mod with only legacy versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        expect($mod->hasOnlyLegacyVersions())->toBeTrue();
    });

    it('returns false for mod with modern versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        $modernVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        expect($mod->hasOnlyLegacyVersions())->toBeFalse();
    });

    it('returns false for mod with both legacy and modern versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        $modernVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        expect($mod->hasOnlyLegacyVersions())->toBeFalse();
    });

    it('returns false for mod with no publicly visible versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        // Unpublished legacy version
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => null,
            'disabled' => false,
        ]);

        expect($mod->hasOnlyLegacyVersions())->toBeFalse();
    });
});

describe('Mod latestLegacyVersion relationship', function (): void {
    it('returns the latest legacy version ordered by semantic version', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        $v1 = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.0.0',
        ]);

        $v2 = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '2.0.0',
        ]);

        $v15 = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.5.0',
        ]);

        $mod->refresh();

        expect($mod->latestLegacyVersion->id)->toBe($v2->id);
    });

    it('does not return disabled legacy versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        // Disabled version (higher number)
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => true,
            'version' => '2.0.0',
        ]);

        // Enabled version (lower number)
        $enabled = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.0.0',
        ]);

        $mod->refresh();

        expect($mod->latestLegacyVersion->id)->toBe($enabled->id);
    });

    it('does not return unpublished legacy versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        // Unpublished version (higher number)
        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => null,
            'disabled' => false,
            'version' => '2.0.0',
        ]);

        // Published version (lower number)
        $published = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.0.0',
        ]);

        $mod->refresh();

        expect($mod->latestLegacyVersion->id)->toBe($published->id);
    });

    it('does not return non-legacy versions', function (): void {
        $sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
        $mod = Mod::factory()->create(['published_at' => now()]);

        // Non-legacy version (higher number)
        $modern = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '2.0.0',
        ]);
        $modern->sptVersions()->sync($sptVersion->id);

        // Legacy version (lower number)
        $legacy = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.0.0',
        ]);

        $mod->refresh();

        expect($mod->latestLegacyVersion->id)->toBe($legacy->id);
    });
});

describe('Mod isPubliclyVisible with legacy versions', function (): void {
    beforeEach(function (): void {
        $this->sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
    });

    it('returns true for mod with only legacy versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        expect($mod->isPubliclyVisible())->toBeTrue();
    });

    it('returns true for mod with modern versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        $modernVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        expect($mod->isPubliclyVisible())->toBeTrue();
    });

    it('returns false for disabled mod even with legacy versions', function (): void {
        $mod = Mod::factory()->create([
            'published_at' => now(),
            'disabled' => true,
        ]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        expect($mod->isPubliclyVisible())->toBeFalse();
    });

    it('returns false for unpublished mod even with legacy versions', function (): void {
        $mod = Mod::factory()->create(['published_at' => null]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        expect($mod->isPubliclyVisible())->toBeFalse();
    });
});

describe('ModFilter legacy versions', function (): void {
    beforeEach(function (): void {
        $this->sptVersion = SptVersion::factory()->create([
            'version' => '3.8.0',
            'mod_count' => 10,
        ]);
    });

    it('excludes legacy-only mods by default', function (): void {
        // Legacy-only mod
        $legacyMod = Mod::factory()->create(['published_at' => now()]);
        ModVersion::factory()->recycle($legacyMod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Modern mod
        $modernMod = Mod::factory()->create(['published_at' => now()]);
        $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        // Default filter (no sptVersions filter = uses default SPT versions)
        $filters = new ModFilter(['sptVersions' => ['3.8.0']]);
        $results = $filters->apply()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($modernMod->id);
    });

    it('includes legacy mods when legacy filter is selected', function (): void {
        // Legacy-only mod
        $legacyMod = Mod::factory()->create(['published_at' => now()]);
        ModVersion::factory()->recycle($legacyMod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Modern mod
        $modernMod = Mod::factory()->create(['published_at' => now()]);
        $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        // Filter with legacy only
        $filters = new ModFilter(['sptVersions' => ['legacy']]);
        $results = $filters->apply()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($legacyMod->id);
    });

    it('includes both legacy and modern mods when both filters are selected', function (): void {
        // Legacy-only mod
        $legacyMod = Mod::factory()->create(['published_at' => now()]);
        ModVersion::factory()->recycle($legacyMod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Modern mod
        $modernMod = Mod::factory()->create(['published_at' => now()]);
        $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        // Filter with both legacy and specific version
        $filters = new ModFilter(['sptVersions' => ['legacy', '3.8.0']]);
        $results = $filters->apply()->get();

        expect($results)->toHaveCount(2);
        expect($results->pluck('id')->toArray())->toContain($legacyMod->id, $modernMod->id);
    });
});

describe('API include_legacy filter', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
    });

    it('excludes legacy-only mods by default', function (): void {
        // Legacy-only mod
        $legacyMod = Mod::factory()->create(['published_at' => now()]);
        ModVersion::factory()->recycle($legacyMod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Modern mod
        $modernMod = Mod::factory()->create(['published_at' => now()]);
        $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $modernMod->id);
    });

    it('includes legacy mods when include_legacy=true', function (): void {
        // Legacy-only mod
        $legacyMod = Mod::factory()->create(['published_at' => now()]);
        ModVersion::factory()->recycle($legacyMod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Modern mod
        $modernMod = Mod::factory()->create(['published_at' => now()]);
        $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[include_legacy]=true');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($returnedIds)->toContain($legacyMod->id, $modernMod->id);
    });

    it('excludes legacy mods when include_legacy=false', function (): void {
        // Legacy-only mod
        $legacyMod = Mod::factory()->create(['published_at' => now()]);
        ModVersion::factory()->recycle($legacyMod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        // Modern mod
        $modernMod = Mod::factory()->create(['published_at' => now()]);
        $modernVersion = ModVersion::factory()->recycle($modernMod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);
        $modernVersion->sptVersions()->sync($this->sptVersion->id);

        $response = $this->withToken($this->token)->getJson('/api/v0/mods?filter[include_legacy]=false');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $modernMod->id);
    });
});

describe('Legacy mod visibility on detail page', function (): void {
    it('allows access to legacy-only mod detail page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['published_at' => now()]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        $this->actingAs($user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk();
    });

    it('displays Legacy SPT Version badge on legacy mod detail page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create(['published_at' => now()]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.0.0',
        ]);

        $this->actingAs($user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertSee('Legacy SPT Version');
    });

    it('allows guests to access legacy-only mod detail page', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
        ]);

        $this->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk();
    });
});

describe('Legacy versions in versions tab', function (): void {
    it('includes legacy versions in the versions tab query for guests', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        // Create a legacy version
        $legacyVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.0.0',
        ]);

        // Query the versions tab as a guest would
        $versions = $mod->versions()
            ->where(function ($q): void {
                $q->publiclyVisible()
                    ->orWhere(function ($legacy): void {
                        $legacy->legacyPubliclyVisible();
                    });
            })
            ->get();

        expect($versions)->toHaveCount(1);
        expect($versions->first()->id)->toBe($legacyVersion->id);
    });

    it('includes both modern and legacy versions in the versions tab query', function (): void {
        $sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
        $mod = Mod::factory()->create(['published_at' => now()]);

        // Create a legacy version
        $legacyVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '1.0.0',
        ]);

        // Create a modern version
        $modernVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => '3.8.0',
            'published_at' => now()->subDay(),
            'disabled' => false,
            'version' => '2.0.0',
        ]);
        $modernVersion->sptVersions()->sync($sptVersion->id);

        // Query the versions tab as a guest would
        $versions = $mod->versions()
            ->where(function ($q): void {
                $q->publiclyVisible()
                    ->orWhere(function ($legacy): void {
                        $legacy->legacyPubliclyVisible();
                    });
            })
            ->get();

        expect($versions)->toHaveCount(2);
        expect($versions->pluck('id')->toArray())->toContain($legacyVersion->id, $modernVersion->id);
    });
});
