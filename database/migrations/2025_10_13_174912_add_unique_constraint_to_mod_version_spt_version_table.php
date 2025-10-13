<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
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
        DB::statement('
            DELETE mvsp
            FROM mod_version_spt_version mvsp
            INNER JOIN (
                SELECT
                    mod_version_id,
                    spt_version_id,
                    MIN(id) as keep_id
                FROM mod_version_spt_version
                GROUP BY mod_version_id, spt_version_id
                HAVING COUNT(*) > 1
            ) dups ON mvsp.mod_version_id = dups.mod_version_id
                AND mvsp.spt_version_id = dups.spt_version_id
                AND mvsp.id != dups.keep_id
        ');

        // Add unique constraint to prevent future duplicates
        Schema::table('mod_version_spt_version', function (Blueprint $table) {
            $table->unique(['mod_version_id', 'spt_version_id'], 'mod_version_spt_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_version_spt_version', function (Blueprint $table) {
            $table->dropUnique('mod_version_spt_version_unique');
        });
    }
};
