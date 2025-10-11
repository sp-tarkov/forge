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
        Schema::table('spt_versions', function (Blueprint $table): void {
            $table->timestamp('publish_date')->nullable()->after('color_class');
            $table->index('publish_date');

            // Add composite index for efficient filtering
            $table->index(['publish_date', 'version_major', 'version_minor', 'version_patch'], 'spt_versions_publish_version_index');
        });

        // Set all existing SPT versions to be published (a week ago to be safe)
        // This ensures existing versions remain visible after migration
        DB::table('spt_versions')->update([
            'publish_date' => now()->subWeek(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spt_versions', function (Blueprint $table): void {
            $table->dropIndex('spt_versions_publish_version_index');
            $table->dropIndex(['publish_date']);
            $table->dropColumn('publish_date');
        });
    }
};
