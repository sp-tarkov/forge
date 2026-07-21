<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Strip 'v' or 'V' prefix from mod_versions.version column
        DB::table('mod_versions')
            ->where(function (Builder $query): void {
                $query->where('version', 'like', 'v%')->orWhere('version', 'like', 'V%');
            })
            ->update(['version' => DB::raw("REGEXP_REPLACE(version, '^[vV]', '')")]);

        // Strip 'v' or 'V' prefix from addon_versions.version column
        DB::table('addon_versions')
            ->where(function (Builder $query): void {
                $query->where('version', 'like', 'v%')->orWhere('version', 'like', 'V%');
            })
            ->update(['version' => DB::raw("REGEXP_REPLACE(version, '^[vV]', '')")]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible
    }
};
