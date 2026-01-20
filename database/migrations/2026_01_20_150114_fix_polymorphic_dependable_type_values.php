<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The malformed type values (backslashes stripped by MySQL) and their correct values.
     *
     * @var array<string, string>
     */
    private array $typeCorrections = [
        'AppModelsModVersion' => 'App\\Models\\ModVersion',
        'AppModelsAddonVersion' => 'App\\Models\\AddonVersion',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->fixDependenciesTable();
        $this->fixResolvedDependenciesTable();
    }

    /**
     * Reverse the migrations.
     *
     * Note: This is intentionally a no-op. We don't want to revert to malformed data.
     */
    public function down(): void
    {
        // Intentionally empty - we don't want to restore malformed values
    }

    /**
     * Fix the dependable_type values in the dependencies table.
     */
    private function fixDependenciesTable(): void
    {
        foreach ($this->typeCorrections as $malformed => $correct) {
            // Find malformed records that have a corresponding valid record (user already fixed)
            // These should be deleted as they are duplicates
            $duplicateIds = DB::table('dependencies as malformed')
                ->join('dependencies as valid', function ($join) use ($malformed, $correct) {
                    $join->on('malformed.dependable_id', '=', 'valid.dependable_id')
                        ->on('malformed.dependent_mod_id', '=', 'valid.dependent_mod_id')
                        ->where('malformed.dependable_type', '=', $malformed)
                        ->where('valid.dependable_type', '=', $correct);
                })
                ->pluck('malformed.id');

            if ($duplicateIds->isNotEmpty()) {
                DB::table('dependencies')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }

            // Update remaining malformed records to have the correct type
            DB::table('dependencies')
                ->where('dependable_type', $malformed)
                ->update(['dependable_type' => $correct]);
        }
    }

    /**
     * Fix the dependable_type values in the resolved_dependencies table.
     */
    private function fixResolvedDependenciesTable(): void
    {
        foreach ($this->typeCorrections as $malformed => $correct) {
            // Find malformed records that have a corresponding valid record (user already fixed)
            // These should be deleted as they are duplicates
            $duplicateIds = DB::table('resolved_dependencies as malformed')
                ->join('resolved_dependencies as valid', function ($join) use ($malformed, $correct) {
                    $join->on('malformed.dependable_id', '=', 'valid.dependable_id')
                        ->on('malformed.dependency_id', '=', 'valid.dependency_id')
                        ->on('malformed.resolved_mod_version_id', '=', 'valid.resolved_mod_version_id')
                        ->where('malformed.dependable_type', '=', $malformed)
                        ->where('valid.dependable_type', '=', $correct);
                })
                ->pluck('malformed.id');

            if ($duplicateIds->isNotEmpty()) {
                DB::table('resolved_dependencies')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }

            // Update remaining malformed records to have the correct type
            DB::table('resolved_dependencies')
                ->where('dependable_type', $malformed)
                ->update(['dependable_type' => $correct]);
        }
    }
};
