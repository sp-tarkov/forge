<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the Laravel Passport (OAuth server) and Sanctum (personal access token) tables. The v0 API is now open
     * and read-only, so neither authentication stack remains. Socialite's `oauth_connections` table is intentionally
     * left untouched. Children are dropped before their parent `oauth_clients` to satisfy foreign keys.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('oauth_refresh_tokens');
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_auth_codes');
        Schema::dropIfExists('oauth_device_codes');
        Schema::dropIfExists('oauth_client_events');
        Schema::dropIfExists('oauth_clients');
        Schema::dropIfExists('personal_access_tokens');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Intentionally irreversible: the Passport and Sanctum tables are not recreated. The original create migrations
     * remain in version-control history if these tables are ever needed again.
     */
    public function down(): void
    {
        //
    }
};
