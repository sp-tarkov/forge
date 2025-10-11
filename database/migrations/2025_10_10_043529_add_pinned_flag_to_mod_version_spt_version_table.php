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
        Schema::table('mod_version_spt_version', function (Blueprint $table): void {
            $table->boolean('pinned_to_spt_publish')->default(false)->after('spt_version_id');
            $table->timestamps(); // Add timestamps if not already present

            // Add index for efficient filtering of pinned versions
            $table->index(['spt_version_id', 'pinned_to_spt_publish'], 'mvs_spt_pinned_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_version_spt_version', function (Blueprint $table): void {
            $table->dropIndex('mvs_spt_pinned_index');
            $table->dropColumn('pinned_to_spt_publish');
            $table->dropTimestamps();
        });
    }
};
