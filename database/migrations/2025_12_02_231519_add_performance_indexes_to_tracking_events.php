<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            // Add indexes for geographic filtering (used with LIKE queries)
            $table->index('country_name', 'tracking_events_country_name_index');
            $table->index('region_name', 'tracking_events_region_name_index');
            $table->index('city_name', 'tracking_events_city_name_index');

            // Composite index for date range + visitor_id filtering (stats queries)
            $table->index(['created_at', 'visitor_id'], 'tracking_events_created_visitor_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropIndex('tracking_events_country_name_index');
            $table->dropIndex('tracking_events_region_name_index');
            $table->dropIndex('tracking_events_city_name_index');
            $table->dropIndex('tracking_events_created_visitor_index');
        });
    }
};
