<?php

declare(strict_types=1);

use App\Http\Filters\ModFilter;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ModFilter with SPT version publish dates', function (): void {
    it('excludes mods with only unpublished SPT versions for guests', function (): void {
        $category = ModCategory::factory()->create();
        $mod = Mod::factory()->create(['category_id' => $category->id]);
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create published and unpublished SPT versions
        $publishedSpt = SptVersion::factory()->create(['version' => '3.10.0']);
        $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);

        // Mod version only supports unpublished SPT
        $modVersion->sptVersions()->attach($unpublishedSpt->id);

        // Test as guest
        $filter = new ModFilter([]);
        $results = $filter->apply()->get();

        expect($results)->toHaveCount(0);
    });

    it('includes mods with unpublished SPT versions for administrators', function (): void {
        $adminRole = UserRole::factory()->create(['name' => 'Administrator']);
        $admin = User::factory()->create(['user_role_id' => $adminRole->id]);

        $category = ModCategory::factory()->create();
        $mod = Mod::factory()->create(['category_id' => $category->id]);
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create unpublished SPT version
        $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);

        // Mod version only supports unpublished SPT
        $modVersion->sptVersions()->attach($unpublishedSpt->id);

        // Test as admin
        auth()->login($admin);
        $filter = new ModFilter([]);
        $results = $filter->apply()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($mod->id);
    });

    it('shows mods when filtering by published SPT versions', function (): void {
        $category = ModCategory::factory()->create();
        $mod1 = Mod::factory()->create(['category_id' => $category->id, 'name' => 'Published SPT Mod']);
        $mod2 = Mod::factory()->create(['category_id' => $category->id, 'name' => 'Unpublished SPT Mod']);

        $modVersion1 = ModVersion::factory()->create([
            'mod_id' => $mod1->id,
            'published_at' => Carbon::now()->subDay(),
        ]);
        $modVersion2 = ModVersion::factory()->create([
            'mod_id' => $mod2->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create published and unpublished SPT versions
        $publishedSpt = SptVersion::factory()->create(['version' => '3.10.0']);
        $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);

        // First mod supports published SPT
        $modVersion1->sptVersions()->attach($publishedSpt->id);

        // Second mod only supports unpublished SPT
        $modVersion2->sptVersions()->attach($unpublishedSpt->id);

        // Filter by the published SPT version
        $filter = new ModFilter(['sptVersions' => ['3.10.0']]);
        $results = $filter->apply()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->name)->toBe('Published SPT Mod');
    });

    it('excludes mods with unpublished SPT when filtering by that version for guests', function (): void {
        $category = ModCategory::factory()->create();
        $mod = Mod::factory()->create(['category_id' => $category->id]);
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create unpublished SPT version
        $unpublishedSpt = SptVersion::factory()->unpublished()->create(['version' => '3.11.0']);
        $modVersion->sptVersions()->attach($unpublishedSpt->id);

        // Try to filter by the unpublished version as guest
        $filter = new ModFilter(['sptVersions' => ['3.11.0']]);
        $results = $filter->apply()->get();

        expect($results)->toHaveCount(0);
    });

    it('includes mods with scheduled SPT versions after publish date', function (): void {
        $category = ModCategory::factory()->create();
        $mod = Mod::factory()->create(['category_id' => $category->id]);
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create a scheduled SPT version that was published yesterday
        $scheduledSpt = SptVersion::factory()->publishedAt(Carbon::now()->subDay())->create(['version' => '3.11.0']);
        $modVersion->sptVersions()->attach($scheduledSpt->id);

        // Should be visible as it's past the publish date
        $filter = new ModFilter([]);
        $results = $filter->apply()->get();

        expect($results)->toHaveCount(1);
    });

    it('excludes mods with future scheduled SPT versions for guests', function (): void {
        $category = ModCategory::factory()->create();
        $mod = Mod::factory()->create(['category_id' => $category->id]);
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create a scheduled SPT version for tomorrow
        $futureSpt = SptVersion::factory()->scheduled(Carbon::now()->addDay())->create(['version' => '3.11.0']);
        $modVersion->sptVersions()->attach($futureSpt->id);

        // Should not be visible as it's before the publish date
        $filter = new ModFilter([]);
        $results = $filter->apply()->get();

        expect($results)->toHaveCount(0);
    });

    it('handles legacy filter with published versions correctly', function (): void {
        // First create some recent versions to ensure we have 3 minor versions
        SptVersion::factory()->create(['version' => '3.11.0']);
        SptVersion::factory()->create(['version' => '3.10.0']);
        SptVersion::factory()->create(['version' => '3.9.0']);

        $category = ModCategory::factory()->create();
        $mod = Mod::factory()->create(['category_id' => $category->id]);
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create an old published version not in the last 3 minors
        $legacySpt = SptVersion::factory()->create(['version' => '1.0.0']);
        $modVersion->sptVersions()->attach($legacySpt->id);

        // Debug: check what versions are considered "active"
        $activeSptVersions = SptVersion::getVersionsForLastThreeMinors()->pluck('version')->toArray();
        expect($activeSptVersions)->not->toContain('1.0.0'); // Ensure 1.0.0 is not in active versions

        // Apply legacy filter
        $filter = new ModFilter(['sptVersions' => 'legacy']);
        $results = $filter->apply()->get();

        // Should include the mod with legacy published version
        expect($results)->toHaveCount(1);
    });

    it('excludes legacy unpublished versions for guests', function (): void {
        $category = ModCategory::factory()->create();
        $mod = Mod::factory()->create(['category_id' => $category->id]);
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Carbon::now()->subDay(),
        ]);

        // Create an old unpublished version
        $legacySpt = SptVersion::factory()->unpublished()->create(['version' => '1.0.0']);
        $modVersion->sptVersions()->attach($legacySpt->id);

        // Apply legacy filter
        $filter = new ModFilter(['sptVersions' => 'legacy']);
        $results = $filter->apply()->get();

        // Should not include the mod with unpublished legacy version
        expect($results)->toHaveCount(0);
    });
});
