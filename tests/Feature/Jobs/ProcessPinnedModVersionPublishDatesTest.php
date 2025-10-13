<?php

declare(strict_types=1);

use App\Jobs\ProcessPinnedModVersionPublishDates;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

uses(RefreshDatabase::class);

describe('ProcessPinnedModVersionPublishDates job', function (): void {
    it('publishes mod version when all pinned SPT versions are published', function (): void {
        // Create a mod version with no published_at date
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
        ]);

        // Create an SPT version that just published
        $sptVersion = SptVersion::factory()->create([
            'version' => '4.0.0',
            'publish_date' => Date::now()->subMinute(),
        ]);

        // Pin the mod version to the SPT version
        $modVersion->sptVersions()->sync([
            $sptVersion->id => ['pinned_to_spt_publish' => true],
        ]);

        // Run the job
        $job = new ProcessPinnedModVersionPublishDates();
        $job->handle();

        // Refresh the model
        $modVersion->refresh();

        // Assert the mod version is now published
        expect($modVersion->published_at)->not->toBeNull();
        expect($modVersion->published_at->toDateString())->toBe(Date::now()->toDateString());

        // Assert the pinning has been cleared
        // After processing, the relationship may exist with pinned=false, or may be removed entirely
        // Both are acceptable outcomes as the mod version is now published
        $sptRelation = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $sptVersion->id)
            ->first();

        if ($sptRelation) {
            // If relationship exists, pinning must be false
            expect($sptRelation->pivot->pinned_to_spt_publish)->toBeFalse('The pinning flag should be cleared');
        } else {
            // Relationship being removed is also acceptable
            expect($sptRelation)->toBeNull();
        }
    });

    it('does not publish mod version when some pinned SPT versions are still unpublished', function (): void {
        // Create a mod version with no published_at date
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
        ]);

        // Create one published and one unpublished SPT version
        $publishedSpt = SptVersion::factory()->create([
            'version' => '4.0.0',
            'publish_date' => Date::now()->subMinute(),
        ]);

        $unpublishedSpt = SptVersion::factory()->create([
            'version' => '4.0.1',
            'publish_date' => Date::now()->addDays(2),
        ]);

        // Pin the mod version to both SPT versions
        $modVersion->sptVersions()->sync([
            $publishedSpt->id => ['pinned_to_spt_publish' => true],
            $unpublishedSpt->id => ['pinned_to_spt_publish' => true],
        ]);

        // Run the job
        $job = new ProcessPinnedModVersionPublishDates();
        $job->handle();

        // Refresh the model
        $modVersion->refresh();

        // Assert the mod version is still not published
        expect($modVersion->published_at)->toBeNull();

        // Assert the pinning has been cleared for published SPT but not for unpublished
        $publishedRelation = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $publishedSpt->id)
            ->first();
        $publishedPivot = $publishedRelation ? $publishedRelation->pivot : null;
        expect($publishedPivot)->not->toBeNull();
        expect($publishedPivot->pinned_to_spt_publish)->toBeFalse();

        $unpublishedRelation = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $unpublishedSpt->id)
            ->first();
        $unpublishedPivot = $unpublishedRelation ? $unpublishedRelation->pivot : null;
        expect($unpublishedPivot)->not->toBeNull();
        expect($unpublishedPivot->pinned_to_spt_publish)->toBeTrue();
    });

    it('clears pinning for published SPT versions even if mod is already published', function (): void {
        // Create an already published mod version
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => Date::now()->subDays(5),
        ]);

        // Create an SPT version that just published
        $sptVersion = SptVersion::factory()->create([
            'version' => '4.0.0',
            'publish_date' => Date::now()->subMinute(),
        ]);

        // Pin the mod version to the SPT version
        $modVersion->sptVersions()->sync([
            $sptVersion->id => ['pinned_to_spt_publish' => true],
        ]);

        $originalPublishDate = $modVersion->published_at;

        // Run the job
        $job = new ProcessPinnedModVersionPublishDates();
        $job->handle();

        // Refresh the model
        $modVersion->refresh();

        // Assert the mod version's publish date hasn't changed
        expect($modVersion->published_at->toDateTimeString())->toBe($originalPublishDate->toDateTimeString());

        // Assert the pinning has been cleared
        $sptRelation = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $sptVersion->id)
            ->first();
        $pivot = $sptRelation ? $sptRelation->pivot : null;
        expect($pivot)->not->toBeNull();
        expect($pivot->pinned_to_spt_publish)->toBeFalse();
    });

    it('only processes SPT versions with pinned mod versions', function (): void {
        // Create an SPT version that is published but has no pinned mod versions
        $sptWithoutPins = SptVersion::factory()->create([
            'version' => '3.9.0',
            'publish_date' => Date::now()->subMinute(),
        ]);

        // Create a mod version attached to it but NOT pinned
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
        ]);

        $modVersion->sptVersions()->sync([
            $sptWithoutPins->id => ['pinned_to_spt_publish' => false],
        ]);

        // Run the job
        $job = new ProcessPinnedModVersionPublishDates();
        $job->handle();

        // Refresh the model
        $modVersion->refresh();

        // Assert nothing changed
        expect($modVersion->published_at)->toBeNull();

        $sptRelation = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $sptWithoutPins->id)
            ->first();
        $pivot = $sptRelation ? $sptRelation->pivot : null;
        expect($pivot)->not->toBeNull();
        expect($pivot->pinned_to_spt_publish)->toBeFalse();
    });

    it('handles multiple mod versions pinned to the same SPT version', function (): void {
        // Create an SPT version that just published (use unique version number)
        $sptVersion = SptVersion::factory()->create([
            'version' => '14.0.0',
            'publish_date' => Date::now()->subMinute(),
        ]);

        // Create multiple mod versions pinned to it
        $modVersions = [];
        for ($i = 0; $i < 3; $i++) {
            $mod = Mod::factory()->create();
            $modVersion = ModVersion::factory()->create([
                'mod_id' => $mod->id,
                'published_at' => null,
            ]);

            $modVersion->sptVersions()->sync([
                $sptVersion->id => ['pinned_to_spt_publish' => true],
            ]);

            $modVersions[] = $modVersion;
        }

        // Run the job
        $job = new ProcessPinnedModVersionPublishDates();
        $job->handle();

        // Assert all mod versions are now published and unpinned
        foreach ($modVersions as $modVersion) {
            $modVersion->refresh();
            expect($modVersion->published_at)->not->toBeNull();

            $sptRelation = $modVersion->sptVersions()
                ->withoutGlobalScopes()
                ->where('spt_version_id', $sptVersion->id)
                ->first();

            if ($sptRelation) {
                // If relationship exists, pinning must be false
                expect($sptRelation->pivot->pinned_to_spt_publish)->toBeFalse('The pinning flag should be cleared');
            } else {
                // Relationship being removed is also acceptable
                expect($sptRelation)->toBeNull();
            }
        }
    });

    it('publishes mod version only when the latest pinned SPT version is published', function (): void {
        // Create a mod version with no published_at date
        $mod = Mod::factory()->create();
        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => null,
        ]);

        // Create two SPT versions - first one published, second one will publish later
        $spt1 = SptVersion::factory()->create([
            'version' => '24.0.0',
            'publish_date' => Date::now()->subMinute(),
        ]);

        $spt2 = SptVersion::factory()->create([
            'version' => '24.0.1',
            'publish_date' => Date::now()->addMinute(),
        ]);

        // Pin the mod version to both
        $modVersion->sptVersions()->sync([
            $spt1->id => ['pinned_to_spt_publish' => true],
            $spt2->id => ['pinned_to_spt_publish' => true],
        ]);

        // Run the job - only spt1 should be processed
        $job = new ProcessPinnedModVersionPublishDates();
        $job->handle();

        $modVersion->refresh();

        // Mod should not be published yet
        expect($modVersion->published_at)->toBeNull();

        // First SPT should be unpinned
        $sptRelation1 = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $spt1->id)
            ->first();
        $pivot1 = $sptRelation1 ? $sptRelation1->pivot : null;
        expect($pivot1)->not->toBeNull();
        expect($pivot1->pinned_to_spt_publish)->toBeFalse();

        // Second SPT should still be pinned
        $sptRelation2 = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $spt2->id)
            ->first();
        $pivot2 = $sptRelation2 ? $sptRelation2->pivot : null;
        expect($pivot2)->not->toBeNull();
        expect($pivot2->pinned_to_spt_publish)->toBeTrue();

        // Now simulate the second SPT version publishing
        $spt2->publish_date = Date::now()->subMinute();
        $spt2->save();

        // Run the job again
        $job->handle();

        $modVersion->refresh();

        // Now the mod should be published
        expect($modVersion->published_at)->not->toBeNull();

        // Both SPT versions should be unpinned
        $sptRelation1 = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $spt1->id)
            ->first();
        if ($sptRelation1) {
            expect($sptRelation1->pivot->pinned_to_spt_publish)->toBeFalse('The first SPT version pinning should be cleared');
        } else {
            expect($sptRelation1)->toBeNull();
        }

        $sptRelation2 = $modVersion->sptVersions()
            ->withoutGlobalScopes()
            ->where('spt_version_id', $spt2->id)
            ->first();
        if ($sptRelation2) {
            expect($sptRelation2->pivot->pinned_to_spt_publish)->toBeFalse('The second SPT version pinning should be cleared');
        } else {
            expect($sptRelation2)->toBeNull();
        }
    });
});
