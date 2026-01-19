<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, create version records for all existing comments
        DB::statement("
            INSERT INTO comment_versions (comment_id, body, version_number, created_at)
            SELECT id, body, 1, COALESCE(created_at, NOW())
            FROM comments
            WHERE body IS NOT NULL AND body != ''
        ");

        // Then drop the body column
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('body');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->mediumText('body')->after('commentable_type');
        });

        // Restore body from latest version
        DB::statement('
            UPDATE comments c
            SET body = (
                SELECT cv.body
                FROM comment_versions cv
                WHERE cv.comment_id = c.id
                ORDER BY cv.version_number DESC
                LIMIT 1
            )
        ');
    }
};
