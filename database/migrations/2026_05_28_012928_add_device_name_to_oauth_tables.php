<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds per-device tracking to OAuth tokens so the Connected Apps page can show "Launcher on Desktop-PC, last
     * used 2 hours ago" rows and revoke a single device without invalidating the user's other installations. See
     * ADR 0001 (per-device sessions).
     */
    public function up(): void
    {
        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            $table->string('device_name')->nullable()->after('scopes');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->string('device_name')->nullable()->after('name');
            $table->timestamp('last_used_at')->nullable()->after('updated_at');
            $table->string('last_ip', 45)->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table): void {
            $table->dropColumn(['device_name', 'last_used_at', 'last_ip']);
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table): void {
            $table->dropColumn('device_name');
        });
    }
};
