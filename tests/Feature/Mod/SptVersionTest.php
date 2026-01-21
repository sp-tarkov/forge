<?php

declare(strict_types=1);

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

describe('SPT version latest minor detection', function (): void {
    it("returns true if the version is part of the latest version's minor releases", function (): void {
        SptVersion::factory()->create(['version' => '1.1.1']);
        SptVersion::factory()->create(['version' => '1.2.0']);
        $version = SptVersion::factory()->create(['version' => '1.3.0']);
        SptVersion::factory()->create(['version' => '1.3.2']);
        SptVersion::factory()->create(['version' => '1.3.3']);

        expect($version->isLatestMinor())->toBeTrue();
    });

    it("returns false if the version is not part of the latest version's minor releases", function (): void {
        SptVersion::factory()->create(['version' => '1.1.1']);
        SptVersion::factory()->create(['version' => '1.2.0']);
        $version = SptVersion::factory()->create(['version' => '1.2.1']);
        SptVersion::factory()->create(['version' => '1.3.2']);
        SptVersion::factory()->create(['version' => '1.3.3']);

        expect($version->isLatestMinor())->toBeFalse();
    });

    it('returns false if there is no latest version in the database', function (): void {
        $version = SptVersion::factory()->make(['version' => '1.0.0']);

        expect($version->isLatestMinor())->toBeFalse();
    });
});

describe('SPT version latest minor versions retrieval', function (): void {
    it('returns all patch versions for the latest minor release', function (): void {
        // Create versions for different minor releases
        SptVersion::factory()->create(['version' => '3.10.0']);
        SptVersion::factory()->create(['version' => '3.10.1']);
        SptVersion::factory()->create(['version' => '3.10.2']);

        // Create the latest minor release with multiple patches
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.11.1']);
        SptVersion::factory()->create(['version' => '3.11.2']);
        SptVersion::factory()->create(['version' => '3.11.3']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(4);
        expect($latestMinorVersions->pluck('version')->toArray())
            ->toBe(['3.11.3', '3.11.2', '3.11.1', '3.11.0']);
    });

    it('returns single version when latest minor has only one patch', function (): void {
        // Create versions for older minor release
        SptVersion::factory()->create(['version' => '3.10.0']);
        SptVersion::factory()->create(['version' => '3.10.1']);

        // Create the latest minor release with only one patch
        SptVersion::factory()->create(['version' => '4.0.0']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(1);
        expect($latestMinorVersions->first()->version)->toBe('4.0.0');
    });

    it('excludes version 0.0.0 from results', function (): void {
        SptVersion::factory()->create(['version' => '0.0.0']);
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.11.1']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(2);
        expect($latestMinorVersions->pluck('version')->toArray())
            ->toBe(['3.11.1', '3.11.0']);
    });

    it('orders versions with release versions before pre-release versions', function (): void {
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.11.1-beta']);
        SptVersion::factory()->create(['version' => '3.11.1']);
        SptVersion::factory()->create(['version' => '3.11.2-alpha']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(4);
        // Ordered by patch desc, then release versions before pre-release versions
        expect($latestMinorVersions->pluck('version')->toArray())
            ->toBe(['3.11.2-alpha', '3.11.1', '3.11.1-beta', '3.11.0']);
    });

    it('returns empty collection when no versions exist', function (): void {
        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(0);
    });
});

describe('SPT version publish date visibility', function (): void {
    it('shows published SPT versions to guests', function (): void {
        // Create published and unpublished versions
        $published = SptVersion::factory()->create(['version' => '1.0.0']);
        $unpublished = SptVersion::factory()->unpublished()->create(['version' => '2.0.0']);
        $scheduled = SptVersion::factory()->scheduled()->create(['version' => '3.0.0']);

        // Query as guest (no auth)
        $visibleVersions = SptVersion::query()->pluck('version')->toArray();

        expect($visibleVersions)->toContain('1.0.0');
        expect($visibleVersions)->not->toContain('2.0.0');
        expect($visibleVersions)->not->toContain('3.0.0');
    });

    it('shows all SPT versions to administrators', function (): void {
        // Create admin role and user
        $adminRole = UserRole::factory()->create(['name' => 'Staff']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);

        // Create published and unpublished versions
        $published = SptVersion::factory()->create(['version' => '1.0.0']);
        $unpublished = SptVersion::factory()->unpublished()->create(['version' => '2.0.0']);
        $scheduled = SptVersion::factory()->scheduled()->create(['version' => '3.0.0']);

        // Query as admin
        $this->actingAs($admin);
        $visibleVersions = SptVersion::query()->pluck('version')->toArray();

        expect($visibleVersions)->toContain('1.0.0');
        expect($visibleVersions)->toContain('2.0.0');
        expect($visibleVersions)->toContain('3.0.0');
    });

    it('shows all SPT versions to moderators', function (): void {
        $moderator = User::factory()->moderator()->create();

        // Create published and unpublished versions
        $published = SptVersion::factory()->create(['version' => '1.0.0']);
        $unpublished = SptVersion::factory()->unpublished()->create(['version' => '2.0.0']);
        $scheduled = SptVersion::factory()->scheduled()->create(['version' => '3.0.0']);

        // Query as moderator
        $this->actingAs($moderator);
        $visibleVersions = SptVersion::query()->pluck('version')->toArray();

        expect($visibleVersions)->toContain('1.0.0');
        expect($visibleVersions)->toContain('2.0.0');
        expect($visibleVersions)->toContain('3.0.0');
    });

    it('shows published SPT versions to regular users', function (): void {
        // Create regular user
        $user = User::factory()->create();

        // Create published and unpublished versions
        $published = SptVersion::factory()->create(['version' => '1.0.0']);
        $unpublished = SptVersion::factory()->unpublished()->create(['version' => '2.0.0']);
        $scheduled = SptVersion::factory()->scheduled()->create(['version' => '3.0.0']);

        // Query as regular user
        $this->actingAs($user);
        $visibleVersions = SptVersion::query()->pluck('version')->toArray();

        expect($visibleVersions)->toContain('1.0.0');
        expect($visibleVersions)->not->toContain('2.0.0');
        expect($visibleVersions)->not->toContain('3.0.0');
    });

    it('correctly identifies published vs unpublished status', function (): void {
        $published = SptVersion::factory()->create();
        $unpublished = SptVersion::factory()->unpublished()->create();
        $scheduledFuture = SptVersion::factory()->scheduled()->create();
        $scheduledPast = SptVersion::factory()->publishedAt(Date::now()->subHour())->create();

        expect($published->is_published)->toBeTrue();
        expect($unpublished->is_published)->toBeFalse();
        expect($scheduledFuture->is_published)->toBeFalse();
        expect($scheduledPast->is_published)->toBeTrue();
    });
});

describe('SPT version methods with publish dates', function (): void {
    it('allValidVersions only returns published versions', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        SptVersion::factory()->unpublished()->create(['version' => '2.0.0']);
        SptVersion::factory()->scheduled()->create(['version' => '3.0.0']);

        $versions = SptVersion::allValidVersions();

        expect($versions)->toContain('1.0.0');
        expect($versions)->not->toContain('2.0.0');
        expect($versions)->not->toContain('3.0.0');
    });

    it('allValidVersions with includeUnpublished returns all versions including unpublished', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        SptVersion::factory()->unpublished()->create(['version' => '2.0.0']);
        SptVersion::factory()->scheduled()->create(['version' => '3.0.0']);

        $versions = SptVersion::allValidVersions(includeUnpublished: true);

        expect($versions)->toContain('1.0.0');
        expect($versions)->toContain('2.0.0');
        expect($versions)->toContain('3.0.0');
    });

    it('getLatest only considers published versions', function (): void {
        SptVersion::factory()->create(['version' => '1.0.0']);
        SptVersion::factory()->unpublished()->create(['version' => '3.0.0']); // Latest but unpublished
        SptVersion::factory()->create(['version' => '2.0.0']);

        $latest = SptVersion::getLatest();

        expect($latest->version)->toBe('2.0.0');
    });

    it('getLatestMinorVersions only includes published versions', function (): void {
        // Create published versions
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.11.1']);
        SptVersion::factory()->create(['version' => '3.11.2']);

        // Create unpublished version that would be latest
        SptVersion::factory()->unpublished()->create(['version' => '3.11.3']);

        $latestMinorVersions = SptVersion::getLatestMinorVersions();

        expect($latestMinorVersions)->toHaveCount(3);
        expect($latestMinorVersions->pluck('version')->toArray())
            ->toBe(['3.11.2', '3.11.1', '3.11.0']);
    });
});

describe('ModVersion pinning to SPT version publish dates', function (): void {
    it('identifies when a mod version is pinned to an unpublished SPT version', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);
        $unpublishedSpt = SptVersion::factory()->unpublished()->create();

        // Attach with pinning
        $modVersion->sptVersions()->sync([
            $unpublishedSpt->id => ['pinned_to_spt_publish' => true],
        ]);

        expect($modVersion->isPinnedToUnpublishedSptVersion())->toBeTrue();
    });

    it('returns false when pinned SPT version becomes published', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);
        $sptVersion = SptVersion::factory()->unpublished()->create();

        // Attach with pinning
        $modVersion->sptVersions()->sync([
            $sptVersion->id => ['pinned_to_spt_publish' => true],
        ]);

        // Initially should be pinned to unpublished
        expect($modVersion->isPinnedToUnpublishedSptVersion())->toBeTrue();

        // Publish the SPT version
        $sptVersion->publish_date = Date::now()->subHour();
        $sptVersion->save();

        // Refresh and check again
        $modVersion->refresh();
        expect($modVersion->isPinnedToUnpublishedSptVersion())->toBeFalse();
    });

    it('returns false when not pinned even if SPT is unpublished', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);
        $unpublishedSpt = SptVersion::factory()->unpublished()->create();

        // Attach without pinning
        $modVersion->sptVersions()->sync([
            $unpublishedSpt->id => ['pinned_to_spt_publish' => false],
        ]);

        expect($modVersion->isPinnedToUnpublishedSptVersion())->toBeFalse();
    });

    it('correctly gets the latest pinned SPT publish date to ensure all versions are released', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);

        $spt1 = SptVersion::factory()->scheduled(Date::now()->addDays(5))->create(['version' => '4.0.1']);
        $spt2 = SptVersion::factory()->scheduled(Date::now()->addDays(3))->create(['version' => '4.0.0']);
        $spt3 = SptVersion::factory()->create(['version' => '3.9.0']); // Published

        // Attach with different pinning states
        $modVersion->sptVersions()->sync([
            $spt1->id => ['pinned_to_spt_publish' => true],
            $spt2->id => ['pinned_to_spt_publish' => true],
            $spt3->id => ['pinned_to_spt_publish' => true],
        ]);

        $latestDate = $modVersion->getLatestPinnedSptPublishDate();

        // Should return the latest date (5 days) to ensure mod waits for ALL unpublished versions
        expect($latestDate)->not->toBeNull();
        expect($latestDate->toDateString())->toBe(Date::now()->addDays(5)->toDateString());
    });

    it('returns null when no unpublished pinned SPT versions', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);

        $publishedSpt = SptVersion::factory()->create();

        // Attach published version with pinning
        $modVersion->sptVersions()->sync([$publishedSpt->id => ['pinned_to_spt_publish' => true]]);

        $latestDate = $modVersion->getLatestPinnedSptPublishDate();

        expect($latestDate)->toBeNull();
    });

    it('mod version is not publicly visible when pinned to unpublished SPT', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Date::now()->subDay(),
            'disabled' => false,
        ]);

        $publishedSpt = SptVersion::factory()->create();
        $unpublishedSpt = SptVersion::factory()->unpublished()->create();

        // Attach both versions, but only pin to unpublished
        $modVersion->sptVersions()->sync([
            $publishedSpt->id => ['pinned_to_spt_publish' => false],
            $unpublishedSpt->id => ['pinned_to_spt_publish' => true],
        ]);

        expect($modVersion->isPubliclyVisible())->toBeFalse();
    });

    it('mod version waits for ALL pinned SPT versions to publish before becoming visible', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Date::now()->subDay(),
            'disabled' => false,
        ]);

        // Create two unpublished SPT versions with different release dates
        $sptTomorrow = SptVersion::factory()->scheduled(Date::now()->addDay())->create(['version' => '4.0.0']);
        $sptInTwoDays = SptVersion::factory()->scheduled(Date::now()->addDays(2))->create(['version' => '4.0.1']);

        // Pin the mod version to both unpublished SPT versions
        $modVersion->sptVersions()->sync([
            $sptTomorrow->id => ['pinned_to_spt_publish' => true],
            $sptInTwoDays->id => ['pinned_to_spt_publish' => true],
        ]);

        // Initially not visible (waiting for both)
        expect($modVersion->isPinnedToUnpublishedSptVersion())->toBeTrue();
        expect($modVersion->isPubliclyVisible())->toBeFalse();

        // Publish only the first SPT version (4.0.0)
        $sptTomorrow->publish_date = Date::now()->subHour();
        $sptTomorrow->save();
        $modVersion->refresh();

        // Should still not be visible (waiting for 4.0.1)
        expect($modVersion->isPinnedToUnpublishedSptVersion())->toBeTrue();
        expect($modVersion->isPubliclyVisible())->toBeFalse();

        // Now publish the second SPT version (4.0.1)
        $sptInTwoDays->publish_date = Date::now()->subHour();
        $sptInTwoDays->save();
        $modVersion->refresh();

        // Should now be visible (all pinned versions are published)
        expect($modVersion->isPinnedToUnpublishedSptVersion())->toBeFalse();
        expect($modVersion->isPubliclyVisible())->toBeTrue();
    });

    it('mod version becomes publicly visible when pinned SPT publishes', function (): void {
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Date::now()->subDay(),
            'disabled' => false,
        ]);

        $sptVersion = SptVersion::factory()->unpublished()->create();

        // Attach with pinning
        $modVersion->sptVersions()->sync([$sptVersion->id => ['pinned_to_spt_publish' => true]]);

        // Initially not visible
        expect($modVersion->isPubliclyVisible())->toBeFalse();

        // Publish the SPT version
        $sptVersion->publish_date = Date::now()->subHour();
        $sptVersion->save();

        // Should now be visible
        $modVersion->refresh();
        expect($modVersion->isPubliclyVisible())->toBeTrue();
    });
});

describe('SPT version cache management', function (): void {
    it('clears cache when SPT version is created', function (): void {
        // Warm the cache
        SptVersion::allValidVersions();
        SptVersion::allValidVersions(includeUnpublished: true);

        // Create a new SPT version
        $version = SptVersion::factory()->create(['version' => '99.99.99']);

        // Cache should be cleared, so this will rebuild it
        $versions = SptVersion::allValidVersions();
        $authorsVersions = SptVersion::allValidVersions(includeUnpublished: true);

        expect($versions)->toContain('99.99.99');
        expect($authorsVersions)->toContain('99.99.99');
    });

    it('clears cache when SPT version publish_date is updated', function (): void {
        $version = SptVersion::factory()->unpublished()->create(['version' => '88.88.88']);

        // Warm the cache
        $versions = SptVersion::allValidVersions();
        expect($versions)->not->toContain('88.88.88');

        // Publish the version
        $version->publish_date = Date::now()->subHour();
        $version->save();

        // Cache should be rebuilt
        $versions = SptVersion::allValidVersions();
        expect($versions)->toContain('88.88.88');
    });
});

describe('Mod filtering with SPT version caching', function (): void {
    beforeEach(function (): void {
        // Clear all caches before each test
        Cache::flush();

        // Create UserRoles if they don't exist
        UserRole::query()->firstOrCreate(['name' => 'Staff'], [
            'short_name' => 'Staff',
            'description' => 'Full access',
            'color_class' => 'sky',
        ]);

        UserRole::query()->firstOrCreate(['name' => 'Moderator'], [
            'short_name' => 'Mod',
            'description' => 'Moderator access',
            'color_class' => 'emerald',
        ]);
    });

    it('caches SPT versions differently for regular users and admins', function (): void {
        // Create SPT versions with different publish dates using unique version numbers
        $publishedSpt = SptVersion::factory()->create([
            'version' => '1.5.0',
            'version_major' => 1,
            'version_minor' => 5,
            'version_patch' => 0,
            'publish_date' => now()->subDay(),
        ]);

        $futurePublishedSpt = SptVersion::factory()->create([
            'version' => '1.6.0',
            'version_major' => 1,
            'version_minor' => 6,
            'version_patch' => 0,
            'publish_date' => now()->addDay(),
        ]);

        // Create mods and mod versions for testing with unique names
        // Use withoutEvents to bypass the observer that auto-syncs SPT versions
        $mod1 = Mod::factory()->create(['name' => 'Test Mod Cache 1']);
        $modVersion1 = ModVersion::withoutEvents(fn () => ModVersion::factory()->for($mod1)->create([
            'published_at' => now()->subDay(),
            'spt_version_constraint' => '', // Empty constraint to prevent auto-sync
        ]));
        $modVersion1->sptVersions()->sync($publishedSpt);

        $mod2 = Mod::factory()->create(['name' => 'Test Mod Cache 2']);
        $modVersion2 = ModVersion::withoutEvents(fn () => ModVersion::factory()->for($mod2)->create([
            'published_at' => now()->subDay(),
            'spt_version_constraint' => '', // Empty constraint to prevent auto-sync
        ]));
        $modVersion2->sptVersions()->sync($futurePublishedSpt);

        // Test as guest user with legacy filter to trigger caching
        $guestFilter = new ModFilter([
            'sptVersions' => 'legacy',
        ]);
        $guestFilter->apply()->get();

        // Check that cache was set for guest users
        $guestCacheKey = 'spt-versions:active:user';
        expect(Cache::has($guestCacheKey))->toBeTrue();

        // Test as admin user with legacy filter to trigger caching
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Verify admin is actually logged in
        expect(auth()->check())->toBeTrue()
            ->and(auth()->user()->isModOrAdmin())->toBeTrue();

        $adminFilter = new ModFilter([
            'sptVersions' => 'legacy',
        ]);
        $adminFilter->apply()->get();

        // Check that different cache was set for admin
        $adminCacheKey = 'spt-versions:active:admin';
        expect(Cache::has($adminCacheKey))->toBeTrue();

        // Verify cache keys are different
        $guestCachedVersions = Cache::get($guestCacheKey);
        $adminCachedVersions = Cache::get($adminCacheKey);

        expect($guestCachedVersions)->not->toBe($adminCachedVersions);

        // Now test filtering by name to ensure we only get our test mods
        auth()->logout();
        $allFilter = new ModFilter([
            'query' => 'Test Mod Cache',
        ]);
        $filteredMods = $allFilter->apply()->get();

        // Filter to only our test mods
        $testMods = $filteredMods->filter(fn ($mod): bool => in_array($mod->id, [$mod1->id, $mod2->id]));

        // Guest should only see mod1 (has published SPT version)
        expect($testMods->pluck('id')->toArray())->toContain($mod1->id)
            ->and($testMods->pluck('id')->toArray())->not->toContain($mod2->id);

        // Test as admin with name filter
        $this->actingAs($admin);
        $adminAllFilter = new ModFilter([
            'query' => 'Test Mod Cache',
        ]);
        $adminFilteredMods = $adminAllFilter->apply()->get();

        // Filter to only our test mods
        $adminTestMods = $adminFilteredMods->filter(fn ($mod): bool => in_array($mod->id, [$mod1->id, $mod2->id]));

        // Admin should see both mods
        expect($adminTestMods->pluck('id')->toArray())->toContain($mod1->id)
            ->and($adminTestMods->pluck('id')->toArray())->toContain($mod2->id);
    });

    it('invalidates cache when SPT version is updated', function (): void {
        $sptVersion = SptVersion::factory()->create([
            'version' => '1.7.0',
            'version_major' => 1,
            'version_minor' => 7,
            'version_patch' => 0,
            'publish_date' => now()->subDay(),
        ]);

        // Create filter with legacy to populate cache
        $filter = new ModFilter([
            'sptVersions' => 'legacy',
        ]);
        $filter->apply()->get();

        // Verify cache exists
        expect(Cache::has('spt-versions:active:user'))->toBeTrue();

        // Update SPT version
        $sptVersion->update(['publish_date' => now()->addDay()]);

        // Cache should be cleared by the observer
        expect(Cache::has('spt-versions:active:user'))->toBeFalse()
            ->and(Cache::has('spt-versions:active:admin'))->toBeFalse();
    });

    it('respects publish_date in SPT version queries', function (): void {
        // Create SPT versions with unique version numbers
        $pastSpt = SptVersion::factory()->create([
            'version' => '1.0.0',
            'version_major' => 1,
            'version_minor' => 0,
            'version_patch' => 0,
            'publish_date' => now()->subWeek(),
        ]);

        $todaySpt = SptVersion::factory()->create([
            'version' => '1.1.0',
            'version_major' => 1,
            'version_minor' => 1,
            'version_patch' => 0,
            'publish_date' => today(),
        ]);

        $futureSpt = SptVersion::factory()->create([
            'version' => '1.2.0',
            'version_major' => 1,
            'version_minor' => 2,
            'version_patch' => 0,
            'publish_date' => now()->addWeek(),
        ]);

        $nullDateSpt = SptVersion::factory()->create([
            'version' => '1.3.0',
            'version_major' => 1,
            'version_minor' => 3,
            'version_patch' => 0,
            'publish_date' => null,
        ]);

        // Create mods for each SPT version with unique names using timestamp
        $timestamp = now()->timestamp;
        $uniquePrefix = 'PublishTest'.$timestamp;
        $mods = [];
        $modNames = [
            '1.0.0' => $uniquePrefix.'_Past',
            '1.1.0' => $uniquePrefix.'_Today',
            '1.2.0' => $uniquePrefix.'_Future',
            '1.3.0' => $uniquePrefix.'_Null',
        ];

        foreach ([$pastSpt, $todaySpt, $futureSpt, $nullDateSpt] as $spt) {
            $mod = Mod::factory()->create(['name' => $modNames[$spt->version]]);
            $modVersion = ModVersion::withoutEvents(fn () => ModVersion::factory()->for($mod)->create([
                'published_at' => now()->subDay(),
                'spt_version_constraint' => '',
            ]));
            $modVersion->sptVersions()->sync($spt);
            $mods[$spt->version] = $mod;
        }

        // Test as guest with query filter
        $guestFilter = new ModFilter([
            'query' => $uniquePrefix,  // Filter to our test mods with unique query
        ]);
        $guestMods = $guestFilter->apply()->get();

        // Filter to only our test mods
        $testMods = $guestMods->filter(function ($mod) use ($mods) {
            $modIds = array_map(fn ($m) => $m->id, $mods);

            return in_array($mod->id, $modIds);
        });

        // Guest should only see past and today's SPT versions
        expect($testMods->pluck('id')->toArray())
            ->toContain($mods['1.0.0']->id)
            ->toContain($mods['1.1.0']->id)
            ->and($testMods->pluck('id')->toArray())
            ->not->toContain($mods['1.2.0']->id)
            ->not->toContain($mods['1.3.0']->id);

        // Test as moderator
        $moderator = User::factory()->moderator()->create();
        $this->actingAs($moderator);

        $modFilter = new ModFilter([
            'query' => $uniquePrefix,  // Filter to our test mods with unique query
        ]);
        $modMods = $modFilter->apply()->get();

        // Filter to only our test mods
        $modTestMods = $modMods->filter(function ($mod) use ($mods) {
            $modIds = array_map(fn ($m) => $m->id, $mods);

            return in_array($mod->id, $modIds);
        });

        // Moderator should see all SPT versions
        expect($modTestMods->pluck('id')->toArray())
            ->toContain($mods['1.0.0']->id)
            ->toContain($mods['1.1.0']->id)
            ->toContain($mods['1.2.0']->id)
            ->toContain($mods['1.3.0']->id);
    });
});
