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
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->boolean('discord_notification_sent')->default(false)->index()->after('disabled');
        });

        // Mark all existing mod versions as having their Discord notification sent
        DB::table('mod_versions')->update(['discord_notification_sent' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->dropColumn('discord_notification_sent');
        });
    }
};
