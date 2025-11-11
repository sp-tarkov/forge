<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the table
        Schema::rename('mod_source_code_links', 'source_code_links');

        // Add polymorphic columns
        Schema::table('source_code_links', function (Blueprint $table) {
            $table->string('sourceable_type')->after('id');
            $table->unsignedBigInteger('sourceable_id')->after('sourceable_type');
            $table->index(['sourceable_type', 'sourceable_id']);
        });

        // Migrate existing mod_id data to polymorphic columns
        DB::table('source_code_links')
            ->update([
                'sourceable_type' => 'App\\Models\\Mod',
                'sourceable_id' => DB::raw('mod_id'),
            ]);

        // Drop the old mod_id column and its constraints
        // Note: Foreign key name is still 'mod_source_code_links_mod_id_foreign' after table rename
        Schema::table('source_code_links', function (Blueprint $table) {
            $table->dropForeign('mod_source_code_links_mod_id_foreign');
            $table->dropColumn('mod_id');
        });
    }

    public function down(): void
    {
        // Recreate mod_id column
        Schema::table('source_code_links', function (Blueprint $table) {
            $table->foreignId('mod_id')
                ->after('id')
                ->constrained('mods')
                ->cascadeOnDelete();
        });

        // Migrate data back to mod_id
        DB::table('source_code_links')
            ->where('sourceable_type', 'App\\Models\\Mod')
            ->update([
                'mod_id' => DB::raw('sourceable_id'),
            ]);

        // Drop polymorphic columns
        Schema::table('source_code_links', function (Blueprint $table) {
            $table->dropIndex(['sourceable_type', 'sourceable_id']);
            $table->dropColumn(['sourceable_type', 'sourceable_id']);
        });

        // Rename back
        Schema::rename('source_code_links', 'mod_source_code_links');
    }
};
