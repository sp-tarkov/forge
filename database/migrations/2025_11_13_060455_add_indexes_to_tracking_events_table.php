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
        Schema::table('tracking_events', function (Blueprint $table) {
            // Add individual column index for visitor_id filtering
            $table->index('visitor_id');

            // Add composite index for common query pattern (date range + event filtering)
            $table->index(['created_at', 'event_name']);
        });

        // Add indexes on TEXT columns using raw SQL with prefix length
        DB::statement('CREATE INDEX tracking_events_browser_index ON tracking_events (browser(191))');
        DB::statement('CREATE INDEX tracking_events_platform_index ON tracking_events (platform(191))');
        DB::statement('CREATE INDEX tracking_events_device_index ON tracking_events (device(191))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop TEXT column indexes using raw SQL
        DB::statement('DROP INDEX tracking_events_device_index ON tracking_events');
        DB::statement('DROP INDEX tracking_events_platform_index ON tracking_events');
        DB::statement('DROP INDEX tracking_events_browser_index ON tracking_events');

        Schema::table('tracking_events', function (Blueprint $table) {
            // Drop composite index
            $table->dropIndex(['created_at', 'event_name']);

            // Drop individual index
            $table->dropIndex(['visitor_id']);
        });
    }
};
