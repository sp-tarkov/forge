<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a covering index for the visitor analytics stats aggregate query.
     * With created_at leading, MySQL can efficiently range-scan the date
     * window, then answer COUNT, SUM(CASE visitor_id), and
     * COUNT(DISTINCT country_code) without touching the table data.
     *
     * Also removes the older [created_at, visitor_id] index that is now
     * fully covered by this wider index.
     */
    public function up(): void
    {
        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->index(
                ['created_at', 'visitor_id', 'ip', 'country_code'],
                'tracking_events_stats_covering_index'
            );
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->dropIndex('tracking_events_created_visitor_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->index(['created_at', 'visitor_id'], 'tracking_events_created_visitor_index');
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->dropIndex('tracking_events_stats_covering_index');
        });
    }
};
