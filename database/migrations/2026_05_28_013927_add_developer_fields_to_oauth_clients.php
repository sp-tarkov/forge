<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the optional fields the Developer Portal exposes to third-party app authors: a free-text description
     * shown on the consent screen and an external homepage URL the user can click through to learn more about the
     * app before granting access. See ADR 0001.
     */
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->string('homepage_url')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn(['description', 'homepage_url']);
        });
    }
};
