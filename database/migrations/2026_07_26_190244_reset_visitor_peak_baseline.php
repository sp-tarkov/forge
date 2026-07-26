<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clear the stored visitor peak and its cache entry.
     */
    public function up(): void
    {
        DB::table('visitor_peaks')->delete();

        Cache::forget('peak_visitor_data');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
