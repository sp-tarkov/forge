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
        $maxLength = config('comments.validation.max_length', 10000);

        // Delete all comments that exceed the maximum allowed length
        DB::table('comments')
            ->whereRaw('CHAR_LENGTH(body) > ?', [$maxLength])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot restore deleted comments
    }
};
