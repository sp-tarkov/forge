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
        Schema::create('addon_version_mod_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_version_id')->constrained('addon_versions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('mod_version_id')->constrained('mod_versions')->cascadeOnDelete()->cascadeOnUpdate();

            $table->index(['mod_addon_version_id', 'mod_version_id'], 'mod_addon_version_mod_version_index');
            $table->index(['mod_version_id', 'mod_addon_version_id'], 'mod_version_mod_addon_version_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_version_mod_version');
    }
};
