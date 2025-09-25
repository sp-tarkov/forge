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
        // Add composite indexes for the main mod filtering query
        Schema::table('mods', function (Blueprint $table) {
            $table->index(['disabled', 'featured', 'created_at'], 'mods_filter_composite_index');
            $table->index(['disabled', 'featured', 'updated_at'], 'mods_filter_updated_index');
            $table->index(['disabled', 'downloads'], 'mods_filter_downloads_index');
        });

        // Add composite indexes for mod_versions filtering
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->index(['mod_id', 'disabled', 'published_at', 'id'], 'mod_versions_optimized_filter_index');
        });

        // Add composite index for spt_versions table
        Schema::table('spt_versions', function (Blueprint $table) {
            $table->index(['version', 'id'], 'spt_versions_version_id_index');
        });

        // Optimize mod_version_spt_version pivot table indexes
        Schema::table('mod_version_spt_version', function (Blueprint $table) {
            $table->index(['mod_version_id', 'spt_version_id', 'id'], 'mvs_optimized_join_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->dropIndex('mods_filter_composite_index');
            $table->dropIndex('mods_filter_updated_index');
            $table->dropIndex('mods_filter_downloads_index');
        });

        Schema::table('mod_versions', function (Blueprint $table) {
            $table->dropIndex('mod_versions_optimized_filter_index');
        });

        Schema::table('spt_versions', function (Blueprint $table) {
            $table->dropIndex('spt_versions_version_id_index');
        });

        Schema::table('mod_version_spt_version', function (Blueprint $table) {
            $table->dropIndex('mvs_optimized_join_index');
        });
    }
};
