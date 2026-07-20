<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Abort if lowercasing would collapse two distinct GUIDs into one. There is no unique index yet, so a
        // collision could otherwise be silently created; surface it for manual resolution instead.
        $collisions = DB::table('mods')
            ->whereNotNull('guid')
            ->where('guid', '!=', '')
            ->selectRaw('LOWER(guid) as lower_guid')
            ->groupBy('lower_guid')
            ->havingRaw('COUNT(DISTINCT guid) > 1')
            ->pluck('lower_guid');

        if ($collisions->isNotEmpty()) {
            throw new RuntimeException('GUID case-insensitive collisions detected; resolve before normalizing: '.$collisions->implode(', '));
        }

        // Lowercase existing values (mb-safe) while the column is still NOT NULL and case-sensitive.
        DB::table('mods')
            ->whereNotNull('guid')
            ->where('guid', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($mods): void {
                foreach ($mods as $mod) {
                    if (! is_string($mod->guid)) {
                        continue;
                    }

                    $lowered = Str::lower($mod->guid);

                    if ($lowered !== $mod->guid) {
                        DB::table('mods')->where('id', $mod->id)->update(['guid' => $lowered]);
                    }
                }
            });

        // Make the column nullable, drop the empty-string default, and on MySQL revert to the database default
        // case-insensitive collation so GUID matching and uniqueness ignore case.
        Schema::table('mods', function (Blueprint $table): void {
            $column = $table->string('guid')->nullable()->default(null);

            if (DB::getDriverName() === 'mysql') {
                $column->charset('utf8mb4')->collation('utf8mb4_0900_ai_ci');
            }

            $column->change();
        });

        // Convert legacy empty strings to null so the unique index can treat "no GUID" rows as distinct.
        DB::table('mods')->where('guid', '')->update(['guid' => null]);

        // Enforce uniqueness at the database level. MySQL permits multiple nulls but only one of any real value.
        Schema::table('mods', function (Blueprint $table): void {
            $table->unique('guid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * The lowercasing of existing data is not reversible; this restores the previous column structure and collation
     * only.
     */
    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table): void {
            $table->dropUnique(['guid']);
        });

        DB::table('mods')->whereNull('guid')->update(['guid' => '']);

        Schema::table('mods', function (Blueprint $table): void {
            $column = $table->string('guid')->default('');

            if (DB::getDriverName() === 'mysql') {
                $column->charset('utf8mb4')->collation('utf8mb4_0900_as_cs');
            }

            $column->change();
        });
    }
};
