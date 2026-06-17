<?php

declare(strict_types=1);

use App\Models\ModVersion;
use App\Services\SptVersionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find and update mod versions with spt_version_constraint = '0.0.0'
        $modVersionsWithLegacyConstraint = DB::table('mod_versions')
            ->where('spt_version_constraint', '0.0.0')
            ->pluck('id')
            ->all();

        if (count($modVersionsWithLegacyConstraint) > 0) {
            // Update the constraint to empty string
            DB::table('mod_versions')
                ->where('spt_version_constraint', '0.0.0')
                ->update(['spt_version_constraint' => '']);
        }

        // Find all pivot entries linked to 0.0.0 SPT version
        $pivotEntries = DB::table('mod_version_spt_version as pivot')
            ->join('spt_versions as sv', 'pivot.spt_version_id', '=', 'sv.id')
            ->where('sv.version', '0.0.0')
            ->select(['pivot.mod_version_id', 'pivot.spt_version_id'])
            ->get();

        $modVersionIds = [];

        if ($pivotEntries->isNotEmpty()) {
            // Get unique mod version IDs for re-resolution
            $modVersionIds = $pivotEntries->pluck('mod_version_id')->unique()->all();

            // Delete all pivot entries linked to 0.0.0
            DB::table('mod_version_spt_version')
                ->whereIn('spt_version_id', function (Builder $query): void {
                    $query->select('id')
                        ->from('spt_versions')
                        ->where('version', '0.0.0');
                })
                ->delete();
        }

        // Re-resolve SPT versions for all affected mod versions
        if (count($modVersionIds) > 0) {
            $sptVersionService = resolve(SptVersionService::class);

            foreach ($modVersionIds as $modVersionId) {
                /** @var ModVersion|null $modVersion */
                $modVersion = ModVersion::query()->withoutGlobalScopes()->find($modVersionId);
                if ($modVersion instanceof ModVersion) {
                    $sptVersionService->resolve($modVersion);
                }
            }
        }

        // Delete the 0.0.0 SPT version record
        DB::table('spt_versions')
            ->where('version', '0.0.0')
            ->delete();
    }

    public function down(): void
    {
        //
    }
};
