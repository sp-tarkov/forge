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

            $table->index('hub_id');
            $table->index('deleted_at');
            $table->index(['spam_status', 'created_at']);
            $table->index('pinned_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
