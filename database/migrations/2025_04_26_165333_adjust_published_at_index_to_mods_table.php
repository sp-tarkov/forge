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
        Schema::table('mods', function (Blueprint $table) {
            $table->dropIndex('mods_filtering_index');
            $table->index('published_at');
            $table->index('disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->dropIndex('mods_published_at_index');
            $table->dropIndex('mods_disabled_index');
            $table->index(['disabled', 'published_at'], 'mods_filtering_index');
        });
    }
};
