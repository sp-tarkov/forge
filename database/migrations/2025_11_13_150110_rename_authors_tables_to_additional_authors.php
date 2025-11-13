<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('mod_authors', 'mod_additional_authors');
        Schema::rename('addon_authors', 'addon_additional_authors');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('mod_additional_authors', 'mod_authors');
        Schema::rename('addon_additional_authors', 'addon_authors');
    }
};
