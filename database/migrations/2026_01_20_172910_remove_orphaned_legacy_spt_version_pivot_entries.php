<?php

declare(strict_types=1);

use App\Models\ModVersion;
use App\Services\SptVersionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            Log::info('[Migration] Found mod versions with legacy 0.0.0 constraint.', [
                'count' => count($modVersionsWithLegacyConstraint),
                'mod_version_ids' => $modVersionsWithLegacyConstraint,
            ]);

            // Update the constraint to empty string
            $updatedCount = DB::table('mod_versions')
                ->where('spt_version_constraint', '0.0.0')
                ->update(['spt_version_constraint' => '']);

            Log::info('[Migration] Updated mod versions with legacy 0.0.0 constraint to empty string.', [
                'updated_count' => $updatedCount,
            ]);
        }

        // Find all pivot entries linked to 0.0.0 SPT version
        $pivotEntries = DB::table('mod_version_spt_version as pivot')
            ->join('spt_versions as sv', 'pivot.spt_version_id', '=', 'sv.id')
            ->where('sv.version', '0.0.0')
            ->select(['pivot.mod_version_id', 'pivot.spt_version_id'])
            ->get();

        $modVersionIds = [];

        if ($pivotEntries->isNotEmpty()) {
            Log::info('[Migration] Found pivot entries linked to 0.0.0 SPT version.', [
                'count' => $pivotEntries->count(),
            ]);

            // Get unique mod version IDs for re-resolution
            $modVersionIds = $pivotEntries->pluck('mod_version_id')->unique()->all();

            // Delete all pivot entries linked to 0.0.0
            $deletedCount = DB::table('mod_version_spt_version')
                ->whereIn('spt_version_id', function ($query): void {
                    $query->select('id')
                        ->from('spt_versions')
                        ->where('version', '0.0.0');
                })
                ->delete();

            Log::info('[Migration] Deleted pivot entries linked to 0.0.0 SPT version.', [
                'deleted_count' => $deletedCount,
            ]);
        } else {
            Log::info('[Migration] No pivot entries linked to 0.0.0 SPT version found.');
        }

        // Re-resolve SPT versions for all affected mod versions
        if (count($modVersionIds) > 0) {
            $sptVersionService = app(SptVersionService::class);

            foreach ($modVersionIds as $modVersionId) {
                $modVersion = ModVersion::withoutGlobalScopes()->find($modVersionId);
                if ($modVersion) {
                    $sptVersionService->resolve($modVersion);
                    Log::info('[Migration] Re-resolved SPT versions for mod version.', [
                        'mod_version_id' => $modVersionId,
                        'constraint' => $modVersion->spt_version_constraint,
                        'resolved_count' => $modVersion->sptVersions()->count(),
                    ]);
                }
            }
        }

        // Delete the 0.0.0 SPT version record
        $deleted = DB::table('spt_versions')
            ->where('version', '0.0.0')
            ->delete();

        if ($deleted > 0) {
            Log::info('[Migration] Deleted the legacy 0.0.0 SPT version record.');
        } else {
            Log::info('[Migration] No 0.0.0 SPT version record found to delete.');
        }
    }

    public function down(): void
    {
        //
    }
};
