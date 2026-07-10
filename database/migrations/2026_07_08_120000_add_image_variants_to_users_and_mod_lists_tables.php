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
        Schema::table('users', function (Blueprint $table): void {
            $table->json('profile_photo_variants')->nullable()->after('profile_photo_path');
            $table->json('cover_photo_variants')->nullable()->after('cover_photo_path');
        });

        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->json('thumbnail_variants')->nullable()->after('thumbnail_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['profile_photo_variants', 'cover_photo_variants']);
        });

        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->dropColumn('thumbnail_variants');
        });
    }
};
