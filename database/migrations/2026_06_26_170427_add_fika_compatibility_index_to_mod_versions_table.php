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
        Schema::table('mod_versions', function (Blueprint $table): void {
            // Serves the mod-level fika_compatibility resolution (the API eager-loads compatible versions by mod_id)
            // and the filter[fika_compatibility] correlated EXISTS subquery. The existing composites all lead with
            // `disabled`, so none covers a fika_compatibility lookup.
            $table->index(
                ['mod_id', 'fika_compatibility', 'published_at'],
                'mod_versions_fika_compatibility_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_versions', function (Blueprint $table): void {
            $table->dropIndex('mod_versions_fika_compatibility_index');
        });
    }
};
