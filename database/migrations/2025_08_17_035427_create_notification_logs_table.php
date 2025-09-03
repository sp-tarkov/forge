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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('notification_type', ['email', 'database', 'all']);
            $table->string('notification_class');
            $table->timestamps();

            // Indexes for performance
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('user_id');
            $table->index('created_at');

            // Prevent duplicate notifications for same notifiable/user/notification combination
            $table->unique(
                ['notifiable_type', 'notifiable_id', 'user_id', 'notification_class'],
                'notification_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
