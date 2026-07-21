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
        Schema::table('tracking_events', function (Blueprint $table): void {
            // Add individual column index for visitor_id filtering
            $table->index('visitor_id');

            // Add composite index for common query pattern (date range + event filtering)
            $table->index(['created_at', 'event_name']);
        });

        // Add indexes on TEXT columns; MySQL requires a prefix length for TEXT indexes, Postgres does not
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX tracking_events_browser_index ON tracking_events (browser(191))');
            DB::statement('CREATE INDEX tracking_events_platform_index ON tracking_events (platform(191))');
            DB::statement('CREATE INDEX tracking_events_device_index ON tracking_events (device(191))');
        } else {
            Schema::table('tracking_events', function (Blueprint $table): void {
                $table->index('browser', 'tracking_events_browser_index');
                $table->index('platform', 'tracking_events_platform_index');
                $table->index('device', 'tracking_events_device_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table): void {
            // Drop TEXT column indexes by name
            $table->dropIndex('tracking_events_device_index');
            $table->dropIndex('tracking_events_platform_index');
            $table->dropIndex('tracking_events_browser_index');

            // Drop composite index
            $table->dropIndex(['created_at', 'event_name']);

            // Drop individual index
            $table->dropIndex(['visitor_id']);
        });
    }
};
