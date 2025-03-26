<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_version_id')->constrained('mod_versions')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('dependent_mod_id')->constrained('mods')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('constraint');
            $table->timestamps();

            $table->index(['mod_version_id', 'dependent_mod_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_dependencies');
    }
};
