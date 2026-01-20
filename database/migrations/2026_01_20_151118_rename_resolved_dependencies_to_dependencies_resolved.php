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
        // Drop the old index before renaming
        Schema::table('resolved_dependencies', function (Blueprint $table): void {
            $table->dropIndex('resolved_dependencies_dependable_index');
        });

        // Rename the table
        Schema::rename('resolved_dependencies', 'dependencies_resolved');

        // Add the new index with the correct name
        Schema::table('dependencies_resolved', function (Blueprint $table): void {
            $table->index(['dependable_id', 'dependable_type', 'dependency_id'], 'dependencies_resolved_dependable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new index
        Schema::table('dependencies_resolved', function (Blueprint $table): void {
            $table->dropIndex('dependencies_resolved_dependable_index');
        });

        // Rename the table back
        Schema::rename('dependencies_resolved', 'resolved_dependencies');

        // Add the old index back
        Schema::table('resolved_dependencies', function (Blueprint $table): void {
            $table->index(['dependable_id', 'dependable_type', 'dependency_id'], 'resolved_dependencies_dependable_index');
        });
    }
};
