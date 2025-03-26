<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spt_versions', function (Blueprint $table) {
            $table->renameColumn('version_pre_release', 'version_labels');
            $table->dropIndex('spt_versions_lookup_index');
            $table->index(['version_major', 'version_minor', 'version_patch', 'version_labels'], 'spt_versions_lookup_index');
        });

        Schema::table('mod_versions', function (Blueprint $table) {
            $table->renameColumn('version_pre_release', 'version_labels');
            $table->dropIndex('mod_versions_version_components_index');
            $table->index(['version_major', 'version_minor', 'version_patch', 'version_labels'], 'mod_versions_version_components_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spt_versions', function (Blueprint $table) {
            $table->renameColumn('version_labels', 'version_pre_release');
            $table->dropIndex('spt_versions_lookup_index');
            $table->index(['version_major', 'version_minor', 'version_patch', 'version_pre_release'], 'spt_versions_lookup_index');
        });

        Schema::table('mod_versions', function (Blueprint $table) {
            $table->renameColumn('version_labels', 'version_pre_release');
            $table->dropIndex('mod_versions_version_components_index');
            $table->index(['version_major', 'version_minor', 'version_patch', 'version_pre_release'], 'mod_versions_version_components_index');
        });
    }
};
