<?php

declare(strict_types=1);

use App\Jobs\SearchSyncJob;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Services\SptVersionService;
use App\Support\HomepageSectionCache;
use App\Support\VersionMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const string CAP_VERSION = '4.1.0';

    private const array SPT_VERSION_CACHE_KEYS = [
        'spt-versions:all:user',
        'spt-versions:all:additional-authors',
        'spt-versions:active:user',
        'spt-versions:active:admin',
        'spt-versions:filter-ids:user',
        'spt-versions:filter-ids:admin',
    ];

    public function up(): void
    {
        // Caps the SPT constraint of every mod version published before the SPT 4.1.0 release to the range below
        // 4.1.0. Open-ended constraints like "~4.0" and ">=4.0.0" become "~4.0 <4.1.0" and ">=4.0.0 <4.1.0".

        $capVersion = SptVersion::query()
            ->withoutGlobalScopes()
            ->where('version', self::CAP_VERSION)
            ->first();

        if ($capVersion === null) {
            return;
        }

        $cutoff = $capVersion->publish_date ?? $capVersion->created_at;

        $allVersions = SptVersion::query()
            ->withoutGlobalScopes()
            ->get(['version'])
            ->map(fn (SptVersion $sptVersion): string => $sptVersion->version)
            ->all();
        $versionsBelowCap = VersionMatcher::satisfiedBy($allVersions, '<'.self::CAP_VERSION);

        /** @var array<string, string|null> $cappedByConstraint */
        $cappedByConstraint = [];

        /** @var array<string, list<int>> $idsByCappedConstraint */
        $idsByCappedConstraint = [];

        ModVersion::query()
            ->withoutGlobalScopes()
            ->whereNotNull('published_at')
            ->where('published_at', '<', $cutoff)
            ->where('spt_version_constraint', '!=', '')
            ->select(['id', 'spt_version_constraint'])
            ->cursor()
            ->each(function (ModVersion $modVersion) use (&$cappedByConstraint, &$idsByCappedConstraint, $versionsBelowCap): void {
                $constraint = $modVersion->spt_version_constraint;

                if (! array_key_exists($constraint, $cappedByConstraint)) {
                    $cappedByConstraint[$constraint] = $this->capConstraint($constraint, $versionsBelowCap);
                }

                $capped = $cappedByConstraint[$constraint];

                if ($capped !== null) {
                    $idsByCappedConstraint[$capped][] = $modVersion->id;
                }
            });

        if ($idsByCappedConstraint === []) {
            return;
        }

        $now = now();
        $updatedCount = 0;

        foreach ($idsByCappedConstraint as $cappedConstraint => $modVersionIds) {
            foreach (array_chunk($modVersionIds, 500) as $modVersionIdChunk) {
                $updatedCount += DB::table('mod_versions')
                    ->whereIn('id', $modVersionIdChunk)
                    ->update([
                        'spt_version_constraint' => $cappedConstraint,
                        'updated_at' => $now,
                    ]);
            }
        }

        resolve(SptVersionService::class)->resolveAll();

        SptVersion::query()->get()->each(function (SptVersion $sptVersion): void {
            $sptVersion->updateModCount();
        });

        foreach (self::SPT_VERSION_CACHE_KEYS as $cacheKey) {
            Cache::forget($cacheKey);
        }

        HomepageSectionCache::flushModSections();

        dispatch(new SearchSyncJob)->onQueue('default');

        Log::info('Capped open-ended SPT constraints on mod versions published before SPT '.self::CAP_VERSION, [
            'mod_versions' => $updatedCount,
            'distinct_constraints' => count($idsByCappedConstraint),
        ]);
    }

    public function down(): void
    {
        // One-shot data correction with no reversal path.
    }

    /**
     * Append "<4.1.0" to each OR branch of the constraint that reaches 4.1.0 or later. Returns null for constraints
     * that do not match 4.1.0, constraints that match no version below 4.1.0, and capped results that fail validation.
     *
     * @param  array<int, string>  $versionsBelowCap
     */
    private function capConstraint(string $constraint, array $versionsBelowCap): ?string
    {
        if (! VersionMatcher::satisfies(self::CAP_VERSION, $constraint)) {
            return null;
        }

        if (VersionMatcher::satisfiedBy($versionsBelowCap, $constraint) === []) {
            return null;
        }

        $cappedBranches = array_map(
            fn (string $branch): string => VersionMatcher::intersects($branch, '>='.self::CAP_VERSION)
                ? $branch.' <'.self::CAP_VERSION
                : $branch,
            array_map(trim(...), explode('||', $constraint)),
        );

        $capped = implode(' || ', $cappedBranches);

        $stillMatchesCap = VersionMatcher::satisfies(self::CAP_VERSION, $capped);
        $lostLowerVersions = VersionMatcher::satisfiedBy($versionsBelowCap, $capped) !== VersionMatcher::satisfiedBy($versionsBelowCap, $constraint);

        if ($stillMatchesCap || $lostLowerVersions) {
            Log::warning('Skipped capping an SPT constraint that failed validation', [
                'constraint' => $constraint,
                'capped' => $capped,
            ]);

            return null;
        }

        return $capped;
    }
};
