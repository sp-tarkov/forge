<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Unfeature any mods that contain AI content
        DB::table('mods')
            ->where('contains_ai_content', true)
            ->where('featured', true)
            ->update(['featured' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't reverse this operation as it would be unclear which mods
        // should be re-featured if we rolled back this migration
    }
};
