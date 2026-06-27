<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The legacy `peak_visitors` table (one row per recorded peak) was superseded by the single-row `visitor_peaks`
     * table. Before dropping it, preserve the all-time peak: take the highest value across both tables and make sure
     * `visitor_peaks` holds it. With current data this is a no-op because the live table already holds the higher
     * value, but it guarantees the recorded peak can never be lost by this migration.
     */
    public function up(): void
    {
        $legacy = DB::table('peak_visitors')->orderByDesc('count')->first();

        if ($legacy !== null) {
            $legacyCount = $this->toInt($legacy->count);
            $legacyDate = $legacy->created_at;
            $current = DB::table('visitor_peaks')->first();

            if ($current === null) {
                DB::table('visitor_peaks')->insert([
                    'peak_count' => $legacyCount,
                    'peak_date' => $legacyDate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Cache::forget('peak_visitor_data');
            } elseif ($legacyCount > $this->toInt($current->peak_count ?? 0)) {
                DB::table('visitor_peaks')->where('id', $current->id)->update([
                    'peak_count' => $legacyCount,
                    'peak_date' => $legacyDate,
                    'updated_at' => now(),
                ]);

                Cache::forget('peak_visitor_data');
            }
        }

        Schema::dropIfExists('peak_visitors');
    }

    /**
     * Reverse the migrations.
     *
     * Recreates the legacy table schema (empty). The historical per-peak rows are not restorable, but the all-time
     * peak survives in `visitor_peaks`.
     */
    public function down(): void
    {
        Schema::create('peak_visitors', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('count')->index();
            $table->timestamps();
        });
    }

    /**
     * Coerce a loosely-typed database value to an integer, treating non-numeric values as zero.
     */
    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
};
