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
            $table->boolean('is_moderation_action')->default(false)->after('event_data');
            $table->string('reason', 1000)->nullable()->after('is_moderation_action');
            $table->index('is_moderation_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracking_events', function (Blueprint $table) {
            $table->dropIndex(['is_moderation_action']);
            $table->dropColumn(['is_moderation_action', 'reason']);
        });
    }
};
