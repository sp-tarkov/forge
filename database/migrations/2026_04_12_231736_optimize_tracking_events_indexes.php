<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optimize tracking_events table for visitor analytics queries.
 *
 * The analytics stats component runs GROUP BY queries on browser, platform,
 * country_code, and ip filtered by created_at date ranges. Without composite
 * indexes, these result in full table scans on 8M+ rows (26s+ per page load).
 *
 * Changes:
 * 1. Convert browser, platform, device from TEXT to VARCHAR(255). Current max
 *    lengths are 9, 12, 13 chars respectively, so VARCHAR(255) is generous.
 *    TEXT columns cannot be efficiently indexed for GROUP BY operations.
 * 2. Add composite indexes pairing created_at with each grouped column so MySQL
 *    can use index range scans instead of full table scans.
 * 3. Drop redundant single-column indexes that are now covered by composites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_events', function (Blueprint $table): void {
            // Convert TEXT columns to VARCHAR so they can be properly indexed
            $table->string('browser')->nullable()->change();
            $table->string('platform')->nullable()->change();
            $table->string('device')->nullable()->change();
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            // Composite indexes for analytics GROUP BY queries filtered by date range
            $table->index(['created_at', 'browser'], 'tracking_events_created_browser_index');
            $table->index(['created_at', 'platform'], 'tracking_events_created_platform_index');
            $table->index(['created_at', 'country_code', 'country_name'], 'tracking_events_created_country_index');
            $table->index(['created_at', 'ip'], 'tracking_events_created_ip_index');

            // Drop single-column indexes now covered by composites
            $table->dropIndex('tracking_events_browser_index');
            $table->dropIndex('tracking_events_platform_index');
            $table->dropIndex('tracking_events_device_index');
            $table->dropIndex('tracking_events_ip_index');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table): void {
            // Restore single-column indexes
            $table->index('browser', 'tracking_events_browser_index');
            $table->index('platform', 'tracking_events_platform_index');
            $table->index('device', 'tracking_events_device_index');
            $table->index('ip', 'tracking_events_ip_index');

            // Drop composite indexes
            $table->dropIndex('tracking_events_created_browser_index');
            $table->dropIndex('tracking_events_created_platform_index');
            $table->dropIndex('tracking_events_created_country_index');
            $table->dropIndex('tracking_events_created_ip_index');
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            // Revert columns back to TEXT
            $table->text('browser')->nullable()->change();
            $table->text('platform')->nullable()->change();
            $table->text('device')->nullable()->change();
        });
    }
};
