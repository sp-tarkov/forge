<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, delete duplicate entries, keeping only the oldest one for each combination
        $duplicateIds = DB::table('mod_version_spt_version as newer')
            ->join('mod_version_spt_version as older', function (JoinClause $join): void {
                $join->on('newer.mod_version_id', '=', 'older.mod_version_id')
                    ->on('newer.spt_version_id', '=', 'older.spt_version_id')
                    ->whereColumn('newer.id', '>', 'older.id');
            })
            ->distinct()
            ->pluck('newer.id');

        foreach ($duplicateIds->chunk(1000) as $chunk) {
            DB::table('mod_version_spt_version')->whereIn('id', $chunk)->delete();
        }

        // Add unique constraint to prevent future duplicates
        Schema::table('mod_version_spt_version', function (Blueprint $table): void {
            $table->unique(['mod_version_id', 'spt_version_id'], 'mod_version_spt_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_version_spt_version', function (Blueprint $table): void {
            $table->dropUnique('mod_version_spt_version_unique');
        });
    }
};
