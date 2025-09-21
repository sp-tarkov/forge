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
        Schema::create('mod_source_code_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mod_id')
                ->constrained('mods')
                ->cascadeOnDelete();
            $table->string('url');
            $table->string('label')->default('');
            $table->timestamps();

            $table->index('mod_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_source_code_links');
    }
};
