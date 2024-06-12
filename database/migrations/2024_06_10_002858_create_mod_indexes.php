<?php

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
        Schema::table('mods', function (Blueprint $table) {
            $table->index(['deleted_at', 'disabled'], 'mods_show_index');
        });

        Schema::table('mod_versions', function (Blueprint $table) {
            $table->index(['mod_id', 'deleted_at', 'disabled', 'version'], 'mod_versions_filtering_index');
        });

        Schema::table('spt_versions', function (Blueprint $table) {
            $table->index(['version', 'deleted_at'], 'spt_versions_filtering_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->dropIndex('mods_show_index');
            $table->dropIndex('mod_versions_filtering_index');
            $table->dropIndex('spt_versions_filtering_index');
        });
    }
};
