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
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['visitor', 'peak'])->default('visitor')->index();
            $table->string('session_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamp('last_activity')->nullable()->index();
            $table->integer('peak_count')->nullable();
            $table->timestamp('peak_date')->nullable();
            $table->timestamps();

            $table->index(['type', 'last_activity']);
            $table->index(['type', 'session_id']);
            $table->unique(['type', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
