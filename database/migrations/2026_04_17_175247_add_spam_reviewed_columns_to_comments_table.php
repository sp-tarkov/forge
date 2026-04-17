<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->timestamp('spam_reviewed_at')->nullable()->after('spam_recheck_count');
            $table->foreignId('spam_reviewed_by')->nullable()->after('spam_reviewed_at')->constrained('users')->nullOnDelete();

            // Drives the spam-review queue: where spam_status = 'spam' and spam_reviewed_at is null
            $table->index(['spam_status', 'spam_reviewed_at'], 'idx_spam_review_queue');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('idx_spam_review_queue');
            $table->dropConstrainedForeignId('spam_reviewed_by');
            $table->dropColumn('spam_reviewed_at');
        });
    }
};
