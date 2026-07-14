<?php

declare(strict_types=1);

use App\Contracts\DependencyResolver;
use App\Http\Filters\ModFilter;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Dependency;
use App\Models\DependencyResolved;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

describe('version prefix stripping', function (): void {
    it('strips lowercase v prefix from version on save', function (): void {
        $modVersion = ModVersion::factory()->create(['version' => 'v1.2.3']);

        expect($modVersion->version)->toBe('1.2.3');
    });

    it('strips uppercase V prefix from version on save', function (): void {
        $modVersion = ModVersion::factory()->create(['version' => 'V1.2.3']);

        expect($modVersion->version)->toBe('1.2.3');
    });

    it('does not modify version without v prefix', function (): void {
        $modVersion = ModVersion::factory()->create(['version' => '1.2.3']);

        expect($modVersion->version)->toBe('1.2.3');
    });

    it('strips v prefix on update', function (): void {
        $modVersion = ModVersion::factory()->create(['version' => '1.0.0']);

        $modVersion->update(['version' => 'v2.0.0']);

        expect($modVersion->fresh()->version)->toBe('2.0.0');
    });
});

describe('verification eligibility', function (): void {
    it('is eligible when the constraint can match the minimum SPT version or newer', function (): void {
        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~4.0.0']);

        expect($modVersion->isEligibleForVerification())->toBeTrue();
    });

    it('is eligible when the constraint spans versions below and above the minimum', function (): void {
        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '>=3.0.0']);

        expect($modVersion->isEligibleForVerification())->toBeTrue();
    });

    it('is not eligible when the constraint only matches versions below the minimum', function (): void {
        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~3.9.0']);

        expect($modVersion->isEligibleForVerification())->toBeFalse();
    });

    it('is not eligible for a legacy version without an SPT constraint', function (): void {
        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '']);

        expect($modVersion->isEligibleForVerification())->toBeFalse();
    });

    it('respects the configured minimum SPT version', function (): void {
        config()->set('verification.min_spt_version', '5.0.0');

        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~4.0.0']);

        expect($modVersion->isEligibleForVerification())->toBeFalse();
    });
});

describe('SPT version resolution', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
    });

    it('resolves spt versions when mod version is created', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        SptVersion::factory()->create(['version' => '1.1.0']);
        SptVersion::factory()->create(['version' => '1.1.1']);
        SptVersion::factory()->create(['version' => '1.2.0']);

        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

        $modVersion->refresh();

        $sptVersions = $modVersion->sptVersions;

        expect($sptVersions)->toHaveCount(2)
            ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');
    });

    it('resolves spt versions when constraint is updated', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        SptVersion::factory()->create(['version' => '1.1.0']);
        SptVersion::factory()->create(['version' => '1.1.1']);
        SptVersion::factory()->create(['version' => '1.2.0']);

        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

        $modVersion->refresh();

        $sptVersions = $modVersion->sptVersions;

        expect($sptVersions)->toHaveCount(2)
            ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');

        $modVersion->spt_version_constraint = '~1.2.0';
        $modVersion->save();

        $modVersion->refresh();

        $sptVersions = $modVersion->sptVersions;

        expect($sptVersions)->toHaveCount(1)
            ->and($sptVersions->pluck('version'))->toContain('1.2.0');
    });

    it('resolves spt versions when spt version is created', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        SptVersion::factory()->create(['version' => '1.1.0']);
        SptVersion::factory()->create(['version' => '1.1.1']);
        SptVersion::factory()->create(['version' => '1.2.0']);

        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

        $modVersion->refresh();

        $sptVersions = $modVersion->sptVersions;

        expect($sptVersions)->toHaveCount(2)
            ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');

        SptVersion::factory()->create(['version' => '1.1.2']);

        $modVersion->refresh();

        $sptVersions = $modVersion->sptVersions;

        expect($sptVersions)->toHaveCount(3)
            ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1', '1.1.2');
    });

    it('resolves spt versions when spt version is deleted', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        SptVersion::factory()->create(['version' => '1.1.0']);
        SptVersion::factory()->create(['version' => '1.1.1']);
        $sptVersion = SptVersion::factory()->create(['version' => '1.1.2']);

        $modVersion = ModVersion::factory()->create(['spt_version_constraint' => '~1.1.0']);

        $modVersion->refresh();

        $sptVersions = $modVersion->sptVersions;

        expect($sptVersions)->toHaveCount(3)
            ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1', '1.1.2');

        $sptVersion->delete();

        $modVersion->refresh();
        $sptVersions = $modVersion->sptVersions;

        expect($sptVersions)->toHaveCount(2)
            ->and($sptVersions->pluck('version'))->toContain('1.1.0', '1.1.1');
    });
});

describe('mod version publishing', function (): void {
    it('includes only published mod versions', function (): void {
        $publishedMod = ModVersion::factory()->create([
            'published_at' => Date::now()->subDay(),
        ]);
        $unpublishedMod = ModVersion::factory()->create([
            'published_at' => Date::now()->addDay(),
        ]);
        $noPublishedDateMod = ModVersion::factory()->create([
            'published_at' => null,
        ]);

        $all = ModVersion::query()->withoutGlobalScopes()->get();
        expect($all)->toHaveCount(3);

        $mods = ModVersion::all();

        expect($mods)->toHaveCount(1)
            ->and($mods->contains($publishedMod))->toBeTrue()
            ->and($mods->contains($unpublishedMod))->toBeFalse()
            ->and($mods->contains($noPublishedDateMod))->toBeFalse();
    });

    it('handles null published_at as not published', function (): void {
        $modWithNoPublishDate = ModVersion::factory()->create([
            'published_at' => null,
        ]);

        $mods = ModVersion::all();

        expect($mods->contains($modWithNoPublishDate))->toBeFalse();
    });
});

describe('mod version updates', function (): void {
    it('updates the parent mods updated_at column when updated', function (): void {
        $originalDate = now()->subDays(10);
        $version = ModVersion::factory()->create(['updated_at' => $originalDate]);

        $version->downloads++;
        $version->save();

        $version->refresh();

        expect($version->mod->updated_at)->not->toEqual($originalDate)
            ->and($version->mod->updated_at->format('Y-m-d'))->toEqual(now()->format('Y-m-d'));
    });
});

describe('mod version downloads', function (): void {
    it('builds download links using the specified version', function (): void {
        $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
        $modVersion1 = ModVersion::factory()->recycle($mod)->create(['version' => '1.2.3']);
        $modVersion2 = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.0']);
        $modVersion3 = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.4']);

        expect($modVersion1->downloadUrl())->toEqual(sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion1->version))
            ->and($modVersion2->downloadUrl())->toEqual(sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion2->version))
            ->and($modVersion3->downloadUrl())->toEqual(sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion3->version));
    });

    it('increments download counts when downloaded', function (): void {
        $mod = Mod::factory()->create(['downloads' => 0]);
        $modVersion = ModVersion::factory()->recycle($mod)->create(['downloads' => 0]);

        $request = $this->get($modVersion->downloadUrl());
        $request->assertStatus(307);

        $modVersion->refresh();

        expect($modVersion->downloads)->toEqual(1)
            ->and($modVersion->mod->downloads)->toEqual(1);
    });

    it('rate limits download links from being hit', function (): void {
        $spt = SptVersion::factory()->create();
        $mod = Mod::factory()->create(['downloads' => 0]);
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
            'downloads' => 0,
        ]);

        $this->actingAs(User::factory()->create());

        // The first 5 requests should be fine.
        for ($i = 0; $i < 5; $i++) {
            $request = $this->get($modVersion->downloadUrl());
            $request->assertStatus(307);
        }

        // The 6th request should be rate limited.
        $request = $this->get($modVersion->downloadUrl());
        $request->assertStatus(429);

        $modVersion->refresh();

        // The download count should be 5.
        expect($modVersion->downloads)->toEqual(5)
            ->and($modVersion->mod->downloads)->toEqual(5);
    });

    it('does not change the mod or mod version updated date when downloaded', function (): void {
        $spt = SptVersion::factory()->create();
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
        ]);

        $updated = now()->subDays(10);

        // The observers change the updated_at column, so we need to manually update them.
        DB::table('mods')->where('id', $mod->id)->update(['updated_at' => $updated]);
        DB::table('mod_versions')->where('id', $modVersion->id)->update(['updated_at' => $updated]);

        $this->get($modVersion->downloadUrl());

        // Refresh the mod and mod version.
        $mod->refresh();
        $modVersion->refresh();

        $expected = $updated->format('Y-m-d H:i:s');
        expect($mod->updated_at->format('Y-m-d H:i:s'))->toEqual($expected)
            ->and($modVersion->updated_at->format('Y-m-d H:i:s'))->toEqual($expected);
    });

    it('returns 404 when mod does not exist', function (): void {
        $spt = SptVersion::factory()->create();
        $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
        ]);

        // Delete the mod to simulate orphaned mod version
        DB::table('mods')->where('id', $mod->id)->delete();

        $this->get($modVersion->downloadUrl())
            ->assertNotFound();
    });

    it('returns 404 when slug does not match', function (): void {
        $spt = SptVersion::factory()->create();
        $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
        ]);

        // Try to access with wrong slug
        $this->get(sprintf('/mod/download/%s/%s/%s', $mod->id, 'wrong-slug', $modVersion->version))
            ->assertNotFound();
    });

    it('returns 403 when mod is unpublished', function (): void {
        $spt = SptVersion::factory()->create();
        $mod = Mod::factory()->create([
            'slug' => 'unpublished-mod',
            'published_at' => null,
        ]);
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
        ]);

        // Build URL manually since downloadUrl() can't access the unpublished mod via relationship
        $downloadUrl = sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion->version);

        // Attempt to download a version of an unpublished mod should be forbidden
        $this->get($downloadUrl)
            ->assertForbidden();
    });

    it('allows mod owner to download unpublished mod version', function (): void {
        $spt = SptVersion::factory()->create();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $owner->id,
            'slug' => 'unpublished-mod',
            'published_at' => null,
        ]);
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
            'published_at' => null,
        ]);

        $downloadUrl = sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion->version);

        $this->actingAs($owner)
            ->get($downloadUrl)
            ->assertStatus(307);
    });

    it('allows mod author to download unpublished mod version', function (): void {
        $spt = SptVersion::factory()->create();
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $owner->id,
            'slug' => 'unpublished-mod',
            'published_at' => null,
        ]);
        $mod->additionalAuthors()->attach($author);

        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
            'published_at' => null,
        ]);

        $downloadUrl = sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion->version);

        $this->actingAs($author)
            ->get($downloadUrl)
            ->assertStatus(307);
    });

    it('allows mod owner to download disabled mod version', function (): void {
        $spt = SptVersion::factory()->create();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
            'disabled' => true,
        ]);

        $downloadUrl = sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion->version);

        $this->actingAs($owner)
            ->get($downloadUrl)
            ->assertStatus(307);
    });

    it('denies other users from downloading unpublished mod version', function (): void {
        $spt = SptVersion::factory()->create();
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $owner->id,
            'slug' => 'unpublished-mod',
            'published_at' => null,
        ]);
        $modVersion = ModVersion::factory()->recycle($mod)->create([
            'spt_version_constraint' => $spt->version,
            'link' => 'https://refringe.com',
            'published_at' => null,
        ]);

        $downloadUrl = sprintf('/mod/download/%s/%s/%s', $mod->id, $mod->slug, $modVersion->version);

        $this->actingAs($otherUser)
            ->get($downloadUrl)
            ->assertForbidden();
    });
});

describe('Published Version Visibility', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        // Create an SPT version for testing
        $this->sptVersion = SptVersion::factory()->create(['version' => '3.8.0']);
    });

    it('excludes published mods with only unpublished versions from API listing', function (): void {
        // Create a mod that is published
        $publishedMod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Create an unpublished mod version
        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $publishedMod->id,
            'published_at' => null, // Unpublished
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        // Sync the version with an SPT version
        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Create another mod with a published version for comparison
        $validMod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $publishedVersion = ModVersion::factory()->create([
            'mod_id' => $validMod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $publishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Test API endpoint - should only return the mod with a published version
        $response = $this->getJson('/api/v0/mods');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $validMod->id);

        // Ensure the mod with an unpublished version is not returned
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($returnedIds)->not->toContain($publishedMod->id);
    });

    it('excludes published mods with only unpublished versions from Livewire listing', function (): void {
        // Create a mod that is published
        $publishedMod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Create an unpublished mod version
        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $publishedMod->id,
            'published_at' => null, // Unpublished
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        // Sync the version with an SPT version
        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Create another mod with a published version for comparison
        $validMod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $publishedVersion = ModVersion::factory()->create([
            'mod_id' => $validMod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $publishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Test Livewire filter - should only return the mod with a published version
        $filters = new ModFilter([
            'sptVersions' => ['3.8.0'],
        ]);

        $results = $filters->apply()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($validMod->id);

        // Ensure the mod with an unpublished version is not returned
        $returnedIds = $results->pluck('id')->toArray();
        expect($returnedIds)->not->toContain($publishedMod->id);
    });

    it('includes mods with at least one published version', function (): void {
        // Create a mod with multiple versions: one unpublished, one published
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Unpublished version
        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        // Published version
        $publishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        // Sync both versions with an SPT version
        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);
        $publishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Should return the mod because it has at least one published version
        $response = $this->getJson('/api/v0/mods');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mod->id);
    });

    it('excludes mods with disabled versions from regular users', function (): void {
        // Create a mod with only a disabled version
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $disabledVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => true, // Disabled
            'spt_version_constraint' => '3.8.0',
        ]);

        $disabledVersion->sptVersions()->sync($this->sptVersion->id);

        // Regular user should not see the mod
        $response = $this->getJson('/api/v0/mods');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    });

    it('denies access to mod detail page when mod has only unpublished versions', function (): void {
        // Create a published mod
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Create an unpublished mod version
        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null, // Unpublished
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Regular user should be denied access to the mod detail page
        $this->actingAs($this->user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertForbidden();
    });

    it('allows access to mod detail page when mod has at least one published version', function (): void {
        // Create a published mod with a published version
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $publishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $publishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Regular user should have access to the mod detail page
        $this->actingAs($this->user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk();
    });

    it('allows moderators to access mod detail page even with unpublished versions', function (): void {
        // Create a moderator user
        $moderator = User::factory()->moderator()->create();

        // Create a mod with only an unpublished version
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Moderator should have access to the mod detail page
        $this->actingAs($moderator)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk();
    });

    it('excludes mods with only unpublished versions from homepage listings', function (): void {
        // Create a featured mod with only an unpublished version
        $mod = Mod::factory()->create([
            'published_at' => now(),
            'featured' => true,
        ]);

        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Visit the homepage and check it's not in featured mods
        $this->actingAs($this->user)
            ->get('/')
            ->assertDontSee($mod->name);
    });

    it('excludes mods with only unpublished versions from user profile listings', function (): void {
        // Create a user with a mod that has only an unpublished version
        $user = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $user->id,
            'published_at' => now(),
        ]);

        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Visit the user profile and check mod is not listed
        $this->actingAs($this->user)
            ->get(route('user.show', ['userId' => $user->id, 'slug' => $user->slug]))
            ->assertOk()
            ->assertDontSee($mod->name);
    });

    it('shows visibility warnings to mod owners viewing mods with no versions', function (): void {
        // Create a mod with no versions
        $user = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $user->id,
            'published_at' => now(),
        ]);

        // Mod owner should see a warning about no versions
        $this->actingAs($user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertSee('This mod has no versions. Users will be unable to view this mod until a version is created.');
    });

    it('shows visibility warnings to mod owners viewing mods with only unpublished versions', function (): void {
        // Create a mod with only an unpublished version
        $user = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $user->id,
            'published_at' => now(),
        ]);

        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Mod owner should see a warning about unpublished versions
        $this->actingAs($user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertSee('This mod has no published versions. Users will be unable to view this mod until a version is published.');
    });

    it('does not show visibility warnings to regular users viewing valid mods', function (): void {
        // Create a mod with a published version
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $publishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);

        $publishedVersion->sptVersions()->sync($this->sptVersion->id);

        // Regular user should not see any warnings
        $this->actingAs($this->user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertDontSee('Regular users cannot see this mod');
    });

    it('shows visibility warnings to moderators viewing problematic mods', function (): void {
        // Create a moderator user
        $moderator = User::factory()->moderator()->create();

        // Create a disabled mod with no versions
        $mod = Mod::factory()->create([
            'published_at' => now(),
            'disabled' => true,
        ]);

        // Moderator should see a warning about disabled mod
        $this->actingAs($moderator)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertSee('This mod is disabled. Users will be unable to view this mod until it is enabled.')
            ->assertSee('This mod has no versions. Users will be unable to view this mod until a version is created.');
    });

    it('denies access to mod detail page when mod has only disabled versions', function (): void {
        // Create a published mod
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Create a disabled mod version
        $disabledVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => true, // Disabled
            'spt_version_constraint' => '3.8.0',
        ]);

        $disabledVersion->sptVersions()->sync($this->sptVersion->id);

        // Regular user should be denied access to the mod detail page
        $this->actingAs($this->user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertForbidden();
    });

    it('shows visibility warnings to mod owners viewing mods with only disabled versions', function (): void {
        // Create a mod with only a disabled version
        $user = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $user->id,
            'published_at' => now(),
        ]);

        $disabledVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => true, // Disabled
            'spt_version_constraint' => '3.8.0',
        ]);

        $disabledVersion->sptVersions()->sync($this->sptVersion->id);

        // Mod owner should see a warning about disabled versions
        $this->actingAs($user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertSee('This mod has no enabled versions. Users will be unable to view this mod until a version is enabled.');
    });

    it('excludes mods with mixed SPT version scenarios from API listing', function (): void {
        // Create a published mod
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Version 1: Has SPT tags but is unpublished
        $unpublishedVersionWithSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null, // Unpublished
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);
        $unpublishedVersionWithSpt->sptVersions()->sync($this->sptVersion->id);

        // Version 2: Published but has no SPT tags
        $publishedVersionNoSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '', // Empty constraint means no SPT versions
        ]);

        // Version 3: Has SPT tags but is disabled
        $disabledVersionWithSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => true, // Disabled
            'spt_version_constraint' => '3.8.0',
        ]);
        $disabledVersionWithSpt->sptVersions()->sync($this->sptVersion->id);

        // Create a valid mod for comparison
        $validMod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $validVersion = ModVersion::factory()->create([
            'mod_id' => $validMod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);
        $validVersion->sptVersions()->sync($this->sptVersion->id);

        // Test API endpoint - should only return the valid mod
        $response = $this->getJson('/api/v0/mods');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $validMod->id);

        // Ensure the problematic mod is not returned
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        expect($returnedIds)->not->toContain($mod->id);
    });

    it('allows regular users access to mods with legacy versions', function (): void {
        // Create a published mod with mixed version scenarios
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Version with SPT tags but unpublished
        $unpublishedVersionWithSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);
        $unpublishedVersionWithSpt->sptVersions()->sync($this->sptVersion->id);

        // Published version with no SPT tags (legacy version)
        $publishedVersionNoSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '', // Empty constraint means legacy version
        ]);

        // Regular user should have access because the mod has a publicly visible legacy version
        $this->actingAs($this->user)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk();
    });

    it('allows mod owners to access mods with legacy versions without warnings', function (): void {
        // Create a mod owner (regular user, not admin)
        $owner = User::factory()->create();
        $mod = Mod::factory()->create([
            'owner_id' => $owner->id,
            'published_at' => now(),
        ]);

        // Version with SPT tags but unpublished
        $unpublishedVersionWithSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);
        $unpublishedVersionWithSpt->sptVersions()->sync($this->sptVersion->id);

        // Published version with no SPT tags (legacy version)
        $publishedVersionNoSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '', // Empty constraint means legacy version
        ]);

        // Mod owner should have access and NOT see warning because mod has publicly visible legacy version
        $this->actingAs($owner)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertDontSee('This mod has no valid published SPT versions');
    });

    it('allows administrators to access mods with legacy versions without warnings', function (): void {
        // Create an administrator role
        $admin = User::factory()->admin()->create();

        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Version with SPT tags but unpublished (admin can see this)
        $unpublishedVersionWithSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);
        $unpublishedVersionWithSpt->sptVersions()->sync($this->sptVersion->id);

        // Published version with no SPT tags (legacy version)
        $publishedVersionNoSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '', // Empty constraint means legacy version
        ]);

        // Staff should have access and NOT see warning because mod has publicly visible legacy version
        $this->actingAs($admin)
            ->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]))
            ->assertOk()
            ->assertDontSee('This mod has no valid published SPT versions');
    });

    it('excludes mods with mixed SPT version scenarios from search indexing', function (): void {
        // Create a mod with mixed SPT version scenarios
        $mod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        // Create active SPT versions for search (must have mod_count > 0)
        $sptVersion = SptVersion::factory()->create([
            'version' => '4.0.0',
            'mod_count' => 5, // Required for getVersionsForLastThreeMinors()
        ]);

        // Version with SPT tags but unpublished
        $unpublishedVersionWithSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
            'disabled' => false,
            'spt_version_constraint' => '4.0.0',
        ]);
        $unpublishedVersionWithSpt->sptVersions()->sync($sptVersion->id);

        // Published version with no SPT tags
        $publishedVersionNoSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '', // Empty constraint means no SPT versions
        ]);

        // Create a valid mod for comparison
        $validMod = Mod::factory()->create([
            'published_at' => now(),
        ]);

        $validVersion = ModVersion::factory()->create([
            'mod_id' => $validMod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '4.0.0',
        ]);
        $validVersion->sptVersions()->sync($sptVersion->id);

        // Clear cache to ensure our new SPT version is included
        Cache::forget('active_spt_versions_for_search');

        // Test search indexing - problematic mod should NOT be searchable
        expect($mod->shouldBeSearchable())->toBeFalse();

        // Valid mod should be searchable
        expect($validMod->shouldBeSearchable())->toBeTrue();
    });

    it('filters versions using the publiclyVisible scope', function (): void {
        $mod = Mod::factory()->create(['published_at' => now()]);

        // Create various types of versions
        $validVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);
        $validVersion->sptVersions()->sync($this->sptVersion->id);

        $unpublishedVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null, // Unpublished
            'disabled' => false,
            'spt_version_constraint' => '3.8.0',
        ]);
        $unpublishedVersion->sptVersions()->sync($this->sptVersion->id);

        $disabledVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => true, // Disabled
            'spt_version_constraint' => '3.8.0',
        ]);
        $disabledVersion->sptVersions()->sync($this->sptVersion->id);

        $versionWithoutSpt = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '', // No SPT versions
        ]);

        // Test the scope - should only return the publicly visible version
        $publicVersions = $mod->versions()->publiclyVisible()->get();

        expect($publicVersions)->toHaveCount(1);
        expect($publicVersions->first()->id)->toBe($validVersion->id);

        // Test the individual method
        expect($validVersion->isPubliclyVisible())->toBeTrue();
        expect($unpublishedVersion->isPubliclyVisible())->toBeFalse();
        expect($disabledVersion->isPubliclyVisible())->toBeFalse();
        expect($versionWithoutSpt->isPubliclyVisible())->toBeFalse();
    });

    it('is not publicly visible when constraint does not resolve even if legacy 0.0.0 version exists', function (): void {
        // Create the legacy 0.0.0 SPT version that exists in production
        SptVersion::factory()->state(['version' => '0.0.0'])->create();

        $mod = Mod::factory()->create();

        // Create a mod version with a constraint that doesn't match any real SPT version
        // It should NOT be visible, even with the 0.0.0 fallback available
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
            'disabled' => false,
            'spt_version_constraint' => '~3.6.0', // No 3.6.x versions exist
        ]);

        // The mod version should NOT have any SPT versions linked
        expect($modVersion->sptVersions)->toHaveCount(0);
        expect($modVersion->latestSptVersion)->toBeNull();
        expect($modVersion->isPubliclyVisible())->toBeFalse();

        // The mod should also NOT be publicly visible
        expect($mod->isPubliclyVisible())->toBeFalse();
    });
});

describe('Dependencies', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
    });

    describe('Dependency Resolution', function (): void {
        it('resolves mod version dependencies on create', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // Should resolve to dependentVersion1
            ]);

            // Check that the resolved dependency has been created
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->first())
                ->not()->toBeNull()
                ->resolved_mod_version_id->toBe($dependentVersion1->id);
        });

        it('resolves multiple matching versions', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.1.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion3 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // Should resolve to dependentVersion1 and dependentVersion2
            ]);

            $dependenciesResolved = DependencyResolved::query()->where('dependable_id', $modVersion->id)->get();

            expect($dependenciesResolved->count())->toBe(2)
                ->and($dependenciesResolved->pluck('resolved_mod_version_id'))
                ->toContain($dependentVersion1->id)
                ->toContain($dependentVersion2->id);
        });

        it('does not resolve dependencies when no versions match', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);
            ModVersion::factory()->recycle($dependentMod)->create(['version' => '3.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // No versions match
            ]);

            // Check that no resolved dependencies were created
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('updates resolved dependencies when constraint changes', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency with an initial constraint
            $dependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0', // Should resolve to dependentVersion1
            ]);

            $resolvedDependency = DependencyResolved::query()->where('dependable_id', $modVersion->id)->first();
            expect($resolvedDependency->resolved_mod_version_id)->toBe($dependentVersion1->id);

            // Update the constraint
            $dependency->update(['constraint' => '^2.0']); // Should now resolve to dependentVersion2

            $resolvedDependency = DependencyResolved::query()->where('dependable_id', $modVersion->id)->first();
            expect($resolvedDependency->resolved_mod_version_id)->toBe($dependentVersion2->id);
        });

        it('removes resolved dependencies when dependency is removed', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();
            $modVersion = ModVersion::factory()->create();

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency
            $dependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0',
            ]);

            $resolvedDependency = DependencyResolved::query()->where('dependable_id', $modVersion->id)->first();
            expect($resolvedDependency)->not()->toBeNull();

            // Delete the dependency
            $dependency->delete();

            // Check that the resolved dependency is removed
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('handles mod versions with no dependencies gracefully', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $serviceSpy = $this->spy(DependencyResolver::class);

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Check that the service was called and that no resolved dependencies were created.
            $serviceSpy->shouldHaveReceived('resolve');
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('resolves the correct versions with a complex semver constraint', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod = Mod::factory()->create();
            $dependentVersion1 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.2.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion3 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '1.5.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion4 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion5 = ModVersion::factory()->recycle($dependentMod)->create(['version' => '2.5.0', 'spt_version_constraint' => '3.8.0']);

            // Create a complex SemVer constraint
            // Should resolve to dependentVersion2, dependentVersion3, and dependentVersion5
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '>1.0 <2.0 || >=2.5.0 <3.0',
            ]);

            $dependenciesResolved = DependencyResolved::query()->where('dependable_id', $modVersion->id)->pluck('resolved_mod_version_id');

            expect($dependenciesResolved)->toContain($dependentVersion2->id)
                ->toContain($dependentVersion3->id)
                ->toContain($dependentVersion5->id)
                ->not->toContain($dependentVersion1->id)
                ->not->toContain($dependentVersion4->id);
        });

        it('resolves overlapping version constraints from multiple dependencies correctly', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod1 = Mod::factory()->create();
            $dependentVersion1_1 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion1_2 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.5.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod2 = Mod::factory()->create();
            $dependentVersion2_1 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentVersion2_2 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.5.0', 'spt_version_constraint' => '3.8.0']);

            // Create two dependencies with overlapping constraints
            Dependency::factory()->recycle([$modVersion, $dependentMod1])->create([
                'constraint' => '>=1.0 <2.0', // Matches both versions of dependentMod1
            ]);

            Dependency::factory()->recycle([$modVersion, $dependentMod2])->create([
                'constraint' => '>=1.5.0 <2.0.0', // Matches only the second version of dependentMod2
            ]);

            $dependenciesResolved = DependencyResolved::query()->where('dependable_id', $modVersion->id)->get();

            expect($dependenciesResolved->pluck('resolved_mod_version_id'))
                ->toContain($dependentVersion1_1->id)
                ->toContain($dependentVersion1_2->id)
                ->toContain($dependentVersion2_2->id)
                ->not->toContain($dependentVersion2_1->id);
        });

        it('handles the case where a dependent mod has no versions available', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod = Mod::factory()->create();

            // Create a dependency where the dependent mod has no versions.
            Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '>=1.0.0',
            ]);

            // Verify that no versions were resolved.
            expect(DependencyResolved::query()->where('dependable_id', $modVersion->id)->exists())->toBeFalse();
        });

        it('calls DependencyVersionService when a Mod is updated', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $mod = Mod::factory()->create();
            ModVersion::factory(2)->recycle($mod)->create();

            $serviceSpy = $this->spy(DependencyResolver::class);

            $mod->update(['name' => 'New Mod Name']);

            $mod->refresh();

            expect($mod->versions)->toHaveCount(2);
            foreach ($mod->versions as $modVersion) {
                $serviceSpy->shouldReceive('resolve')->with($modVersion);
            }
        });

        it('calls DependencyVersionService when a ModVersion is updated', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $serviceSpy = $this->spy(DependencyResolver::class);

            $modVersion->update(['version' => '1.1.0']);

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('calls DependencyVersionService when a ModVersion is deleted', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            $serviceSpy = $this->spy(DependencyResolver::class);

            $modVersion->delete();

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('calls DependencyVersionService when a ModDependency is updated', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod = Mod::factory()->create();
            $modDependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0',
            ]);

            $serviceSpy = $this->spy(DependencyResolver::class);

            $modDependency->update(['constraint' => '^2.0']);

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('calls DependencyVersionService when a ModDependency is deleted', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $modVersion = ModVersion::factory()->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod = Mod::factory()->create();
            $modDependency = Dependency::factory()->recycle([$modVersion, $dependentMod])->create([
                'constraint' => '^1.0',
            ]);

            $serviceSpy = $this->spy(DependencyResolver::class);

            $modDependency->delete();

            $serviceSpy->shouldHaveReceived('resolve');
        });

        it('does not return duplicate entries from latestDependenciesResolved when duplicate records exist', function (): void {
            $this->withoutDefer();
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            // Create a mod version WITHOUT triggering the observer
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = ModVersion::factory()->recycle($mainMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency mod with a single version
            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $dependencyVersion = ModVersion::factory()->recycle($dependencyMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Manually create ONE dependency
            $dependency = Dependency::factory()->make([
                'dependable_id' => $mainModVersion->id,
                'dependent_mod_id' => $dependencyMod->id,
                'constraint' => '^2.0.0',
            ]);
            $dependency->saveQuietly(); // Skip observer

            // Create TWO identical resolved dependency records (simulating a data integrity issue)
            DependencyResolved::factory()->make([
                'dependable_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $dependencyVersion->id,
            ])->saveQuietly();

            DependencyResolved::factory()->make([
                'dependable_id' => $mainModVersion->id,
                'dependency_id' => $dependency->id,
                'resolved_mod_version_id' => $dependencyVersion->id,
            ])->saveQuietly();

            // Verify we have 2 duplicate resolved dependency records
            expect(DependencyResolved::query()->where('dependable_id', $mainModVersion->id)->count())->toBe(2);

            // latestDependenciesResolved should still return ONLY 1 unique entry (not duplicates)
            $mainModVersion->load('latestDependenciesResolved');
            $latest = $mainModVersion->latestDependenciesResolved;

            expect($latest)->toHaveCount(1)
                ->and($latest->pluck('id')->unique())
                ->toHaveCount(1)
                ->and($latest->first()->version)->toBe('2.0.0');
        });

        it('does not return duplicate entries from latestDependenciesResolved with multiple versions', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            // Create a mod version
            $mainMod = Mod::factory()->create(['name' => 'Main Mod']);
            $mainModVersion = ModVersion::factory()->recycle($mainMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a dependency mod with multiple versions
            $dependencyMod = Mod::factory()->create(['name' => 'Dependency Mod']);
            $dependencyVersion1 = ModVersion::factory()->recycle($dependencyMod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependencyVersion2 = ModVersion::factory()->recycle($dependencyMod)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            // Create a single dependency
            $dependency = Dependency::factory()->recycle([$mainModVersion, $dependencyMod])->create(['constraint' => '>=1.0.0']);

            // The observer should create 2 resolved dependencies (one for each matching version)
            expect(DependencyResolved::query()->where('dependable_id', $mainModVersion->id)->count())->toBe(2);

            // latestDependenciesResolved should return ONLY 1 entry (the latest version)
            $mainModVersion->load('latestDependenciesResolved');
            $latest = $mainModVersion->latestDependenciesResolved;

            expect($latest)->toHaveCount(1)
                ->and($latest->pluck('version')->unique())
                ->toHaveCount(1)
                ->toContain($dependencyVersion2->version);
        });

        it('displays the latest resolved dependencies on the mod detail page', function (): void {
            SptVersion::factory()->state(['version' => '3.8.0'])->create();

            $dependentMod1 = Mod::factory()->create();
            $dependentMod1Version1 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod1Version2 = ModVersion::factory()->recycle($dependentMod1)->create(['version' => '2.0.0', 'spt_version_constraint' => '3.8.0']);

            $dependentMod2 = Mod::factory()->create();
            $dependentMod2Version1 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod2Version2 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.1.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod2Version3 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.2.0', 'spt_version_constraint' => '3.8.0']);
            $dependentMod2Version4 = ModVersion::factory()->recycle($dependentMod2)->create(['version' => '1.2.1', 'spt_version_constraint' => '3.8.0']);

            $mod = Mod::factory()->create();
            $mainModVersion = ModVersion::factory()->recycle($mod)->create(['version' => '1.0.0', 'spt_version_constraint' => '3.8.0']);

            Dependency::factory()->recycle([$mainModVersion, $dependentMod1])->create(['constraint' => '>=1.0.0']);
            Dependency::factory()->recycle([$mainModVersion, $dependentMod2])->create(['constraint' => '>=1.0.0']);

            // Test dependenciesResolved returns all resolved versions
            $mainModVersion->load('dependenciesResolved');
            expect($mainModVersion->dependenciesResolved)->toHaveCount(6)
                ->and($mainModVersion->dependenciesResolved->pluck('version'))
                ->toContain($dependentMod1Version1->version)
                ->toContain($dependentMod1Version2->version)
                ->toContain($dependentMod2Version1->version)
                ->toContain($dependentMod2Version2->version)
                ->toContain($dependentMod2Version3->version)
                ->toContain($dependentMod2Version4->version);

            // Test latestDependenciesResolved returns only the latest version per mod
            $mainModVersion->load('latestDependenciesResolved');
            expect($mainModVersion->latestDependenciesResolved)->toHaveCount(2)
                ->and($mainModVersion->latestDependenciesResolved->pluck('version'))
                ->toContain($dependentMod1Version2->version) // Latest version of dependentMod1
                ->toContain($dependentMod2Version4->version); // Latest version of dependentMod2

            $response = $this->get(route('mod.show', ['modId' => $mod->id, 'slug' => $mod->slug]));

            // The view shows latestDependenciesResolved in the Required Dependencies section
            $response->assertSee(__('Required Dependencies'))
                ->assertSee(__('The latest version of this mod requires the following mods to be installed as well.'))
                ->assertSee($dependentMod1->name)
                ->assertSee(__('Requires').' v'.$dependentMod1Version2->version)
                ->assertSee($dependentMod2->name)
                ->assertSee(__('Requires').' v'.$dependentMod2Version4->version);
        });
    });
});

describe('View Addons Link', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
    });

    it('shows view addons link when mod version has compatible addons', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create an addon for this mod with a compatible version
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertSee('View Addons');
    });

    it('hides view addons link when mod cannot have addons', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => true]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Even if an addon exists (shouldn't happen but let's test)
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });

    it('hides view addons link when only unpublished addons are compatible', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create an unpublished addon
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => null]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });

    it('hides view addons link when only disabled addons are compatible', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create a disabled addon
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'disabled' => true]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });

    it('hides view addons link when only detached addons are compatible', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'addons_disabled' => false]);

        $sptVersion = SptVersion::factory()->create();
        $modVersion = ModVersion::factory()
            ->for($mod)
            ->create(['version' => '1.0.0', 'published_at' => now()]);

        $modVersion->sptVersions()->sync([$sptVersion->id]);

        // Create a detached addon
        $addon = Addon::factory()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['published_at' => now(), 'detached_at' => now()]);

        AddonVersion::factory()
            ->for($addon)
            ->create(['mod_version_constraint' => '^1.0.0', 'published_at' => now()]);

        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', [
                'modId' => $mod->id,
                'slug' => $mod->slug,
            ])
            ->assertDontSee('View Addons');
    });
});
