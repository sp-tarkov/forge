<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add fingerprint and verification status columns to mod_versions and addon_versions tables.
     * These columns enable change detection via HTTP HEAD requests and quick verification status lookups.
     */
    public function up(): void
    {
        Schema::table('mod_versions', function (Blueprint $table): void {
            $table->string('etag')->nullable()->after('content_length');
            $table->string('last_modified_header')->nullable()->after('etag');
            $table->string('verification_status')->nullable()->after('last_modified_header');
            $table->timestamp('last_verified_at')->nullable()->after('verification_status');
        });

        Schema::table('addon_versions', function (Blueprint $table): void {
            $table->string('etag')->nullable()->after('content_length');
            $table->string('last_modified_header')->nullable()->after('etag');
            $table->string('verification_status')->nullable()->after('last_modified_header');
            $table->timestamp('last_verified_at')->nullable()->after('verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_versions', function (Blueprint $table): void {
            $table->dropColumn(['etag', 'last_modified_header', 'verification_status', 'last_verified_at']);
        });

        Schema::table('addon_versions', function (Blueprint $table): void {
            $table->dropColumn(['etag', 'last_modified_header', 'verification_status', 'last_verified_at']);
        });
    }
};
