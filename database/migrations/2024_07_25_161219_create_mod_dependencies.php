<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_version_id')
                ->constrained('mod_versions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('dependency_mod_id')
                ->constrained('mods')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('version_constraint'); // e.g., ^1.0.1
            $table->foreignId('resolved_version_id')
                ->nullable()
                ->constrained('mod_versions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_dependencies');
    }
};
