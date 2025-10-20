<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert legacy $2a$ bcrypt hashes to PHP's $2y$ format
        // Laravel 12's stricter validation rejects $2a$ even though they're cryptographically equivalent
        DB::table('users')
            ->where('password', 'like', '$2a$%')
            ->update([
                'password' => DB::raw("REPLACE(password, '\$2a\$', '\$2y\$')"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Naa
    }
};
