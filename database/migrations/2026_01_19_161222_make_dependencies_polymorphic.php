<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add polymorphic columns to mod_dependencies
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            // Add the polymorphic type column with default for existing records
            $table->string('dependable_type')->default('App\\Models\\ModVersion')->after('id');
            // Add the polymorphic id column (will be populated from mod_version_id)
            $table->unsignedBigInteger('dependable_id')->after('dependable_type');
        });

        // Step 2: Populate dependable_id from mod_version_id
        DB::table('mod_dependencies')->update([
            'dependable_id' => DB::raw('mod_version_id'),
        ]);

        // Step 3: Drop old foreign key and index on mod_dependencies
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            $table->dropForeign(['mod_version_id']);
            $table->dropIndex('mod_dependencies_mod_version_id_dependent_mod_id_index');
        });

        // Step 4: Drop the old mod_version_id column
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            $table->dropColumn('mod_version_id');
        });

        // Step 5: Add new polymorphic index
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            $table->index(['dependable_id', 'dependable_type', 'dependent_mod_id'], 'dependencies_dependable_index');
        });

        // Step 6: Rename the table
        Schema::rename('mod_dependencies', 'dependencies');

        // Step 7: Remove the default from dependable_type (migration complete)
        Schema::table('dependencies', function (Blueprint $table): void {
            $table->string('dependable_type')->default(null)->change();
        });

        // === Now handle mod_resolved_dependencies ===

        // Step 1: Add polymorphic columns to mod_resolved_dependencies
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            // Add the polymorphic type column with default for existing records
            $table->string('dependable_type')->default('App\\Models\\ModVersion')->after('id');
            // Add the polymorphic id column (will be populated from mod_version_id)
            $table->unsignedBigInteger('dependable_id')->after('dependable_type');
        });

        // Step 2: Populate dependable_id from mod_version_id
        DB::table('mod_resolved_dependencies')->update([
            'dependable_id' => DB::raw('mod_version_id'),
        ]);

        // Step 3: Drop old foreign key and index on mod_resolved_dependencies
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->dropForeign(['mod_version_id']);
            $table->dropIndex('mod_resolved_dependencies_mod_version_id_dependency_id_index');
        });

        // Step 4: Drop the old mod_version_id column
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->dropColumn('mod_version_id');
        });

        // Step 5: Add new polymorphic index
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->index(['dependable_id', 'dependable_type', 'dependency_id'], 'resolved_dependencies_dependable_index');
        });

        // Step 6: Rename the table
        Schema::rename('mod_resolved_dependencies', 'resolved_dependencies');

        // Step 7: Remove the default from dependable_type (migration complete)
        Schema::table('resolved_dependencies', function (Blueprint $table): void {
            $table->string('dependable_type')->default(null)->change();
        });

        // Step 8: Update foreign key reference from mod_dependencies to dependencies
        // Note: The foreign key keeps its original name after table rename
        Schema::table('resolved_dependencies', function (Blueprint $table): void {
            $table->dropForeign('mod_resolved_dependencies_dependency_id_foreign');
            $table->foreign('dependency_id')
                ->references('id')
                ->on('dependencies')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // === Reverse resolved_dependencies ===

        // Step 1: Update foreign key reference back to mod_dependencies
        Schema::table('resolved_dependencies', function (Blueprint $table): void {
            $table->dropForeign(['dependency_id']);
        });

        // Rename back
        Schema::rename('resolved_dependencies', 'mod_resolved_dependencies');

        // Drop new index
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->dropIndex('resolved_dependencies_dependable_index');
        });

        // Add back mod_version_id column
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->unsignedBigInteger('mod_version_id')->after('id');
        });

        // Populate mod_version_id from dependable_id
        DB::table('mod_resolved_dependencies')
            ->where('dependable_type', 'App\\Models\\ModVersion')
            ->update([
                'mod_version_id' => DB::raw('dependable_id'),
            ]);

        // Drop polymorphic columns
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->dropColumn(['dependable_type', 'dependable_id']);
        });

        // Add back original foreign key and index
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->foreign('mod_version_id')
                ->references('id')
                ->on('mod_versions')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->index(['mod_version_id', 'dependency_id'], 'mod_resolved_dependencies_mod_version_id_dependency_id_index');
        });

        // === Reverse dependencies ===

        // Rename back
        Schema::rename('dependencies', 'mod_dependencies');

        // Drop new index
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            $table->dropIndex('dependencies_dependable_index');
        });

        // Add back mod_version_id column
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            $table->unsignedBigInteger('mod_version_id')->after('id');
        });

        // Populate mod_version_id from dependable_id
        DB::table('mod_dependencies')
            ->where('dependable_type', 'App\\Models\\ModVersion')
            ->update([
                'mod_version_id' => DB::raw('dependable_id'),
            ]);

        // Drop polymorphic columns
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            $table->dropColumn(['dependable_type', 'dependable_id']);
        });

        // Add back original foreign key and index
        Schema::table('mod_dependencies', function (Blueprint $table): void {
            $table->foreign('mod_version_id')
                ->references('id')
                ->on('mod_versions')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->index(['mod_version_id', 'dependent_mod_id'], 'mod_dependencies_mod_version_id_dependent_mod_id_index');
        });

        // Restore foreign key on mod_resolved_dependencies
        Schema::table('mod_resolved_dependencies', function (Blueprint $table): void {
            $table->foreign('dependency_id')
                ->references('id')
                ->on('mod_dependencies')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }
};
