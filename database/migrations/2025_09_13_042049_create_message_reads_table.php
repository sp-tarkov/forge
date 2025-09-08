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
        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at');
            $table->unique(['message_id', 'user_id']);

            // Add indexes for performance
            $table->index(['user_id', 'message_id']);
            $table->index(['message_id', 'read_at']);
        });

        // Drop the old read_at column from messages table
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add read_at column to messages
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable();
        });

        Schema::dropIfExists('message_reads');
    }
};
