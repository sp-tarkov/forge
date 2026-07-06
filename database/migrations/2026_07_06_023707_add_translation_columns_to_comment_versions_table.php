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
        Schema::table('comment_versions', function (Blueprint $table): void {
            $table->string('detected_language', 12)->nullable()->after('version_number');
            $table->mediumText('translated_body')->nullable()->after('detected_language');
            $table->json('translation_metadata')->nullable()->after('translated_body');
            $table->timestamp('language_detected_at')->nullable()->after('translation_metadata');
            $table->timestamp('translated_at')->nullable()->after('language_detected_at');

            $table->index('language_detected_at', 'idx_versions_language_pending');
            $table->index('detected_language', 'idx_versions_translation_pending');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comment_versions', function (Blueprint $table): void {
            $table->dropIndex('idx_versions_language_pending');
            $table->dropIndex('idx_versions_translation_pending');

            $table->dropColumn([
                'detected_language',
                'translated_body',
                'translation_metadata',
                'language_detected_at',
                'translated_at',
            ]);
        });
    }
};
