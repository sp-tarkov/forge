<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_version_spt_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_version_id')->constrained('mod_versions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('spt_version_id')->constrained('spt_versions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['mod_version_id', 'spt_version_id'], 'mod_version_spt_version_index');
            $table->index(['spt_version_id', 'mod_version_id'], 'spt_version_mod_version_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_version_spt_version');
    }
};
