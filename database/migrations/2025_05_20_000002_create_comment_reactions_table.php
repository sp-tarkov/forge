<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'comment_id']);
            $table->unique(['hub_id'], 'comment_reactions_hub_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reactions');
    }
};
