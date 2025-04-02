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
        // Add the owner_id column to the mods table.
        Schema::table('mods', function (Blueprint $table) {
            $table->foreignId('owner_id')
                ->after('hub_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        // Rename the pivot table from mod_user to mod_authors.
        Schema::rename('mod_user', 'mod_authors');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename the pivot table back to mod_user.
        Schema::rename('mod_authors', 'mod_user');

        // Remove the owner_id column and its foreign key constraint from the mods table.
        Schema::table('mods', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
