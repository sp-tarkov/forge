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
        Schema::table('mods', function (Blueprint $table): void {
            $table->longText('description_html')->nullable()->after('description');
        });

        Schema::table('mod_versions', function (Blueprint $table): void {
            $table->longText('description_html')->nullable()->after('description');
        });

        Schema::table('addons', function (Blueprint $table): void {
            $table->longText('description_html')->nullable()->after('description');
        });

        Schema::table('addon_versions', function (Blueprint $table): void {
            $table->longText('description_html')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table): void {
            $table->dropColumn('description_html');
        });

        Schema::table('mod_versions', function (Blueprint $table): void {
            $table->dropColumn('description_html');
        });

        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('description_html');
        });

        Schema::table('addon_versions', function (Blueprint $table): void {
            $table->dropColumn('description_html');
        });
    }
};
