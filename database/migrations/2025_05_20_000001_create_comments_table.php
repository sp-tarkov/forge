<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hub_id')->nullable()->default(null);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('commentable');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->foreignId('root_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->mediumText('body');
            $table->string('user_ip', 65)->default('');
            $table->string('user_agent', 2048)->default('');
            $table->string('referrer', 2048)->default('');
            $table->enum('spam_status', ['pending', 'clean', 'spam'])->default('pending');
            $table->json('spam_metadata')->nullable();
            $table->timestamp('spam_checked_at')->nullable();
            $table->unsignedTinyInteger('spam_recheck_count')->default(0);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamps();

            // Commonly filtered
            $table->index('hub_id');
            $table->index('deleted_at');
            $table->index(['spam_status', 'created_at']);
            $table->index('pinned_at');

            // For rootComments() queries
            $table->index(['commentable_type', 'commentable_id', 'parent_id', 'root_id', 'pinned_at', 'created_at'], 'idx_root_comments_ordering');

            // For descendants() queries, where root_id = X with ordering
            $table->index(['root_id', 'created_at'], 'idx_descendants_by_root');

            // For getDescendantCounts() query, where the commentable_type, commentable_id, and root_id are not null
            $table->index(['commentable_type', 'commentable_id', 'root_id'], 'idx_descendant_counts');

            // For visibleToUser scope, which uses spam_status and user_id for filtering
            $table->index(['spam_status', 'user_id'], 'idx_visible_comments');

            // For validation queries, commentable, and parent lookups
            $table->index(['commentable_type', 'commentable_id', 'id'], 'idx_commentable_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
