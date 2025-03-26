<?php

declare(strict_types=1);

use App\Models\UserRole;
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hub_id')->nullable()->default(null)->unique();
            $table->unsignedBigInteger('discord_id')->nullable()->default(null)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->longText('about')->nullable()->default(null);
            $table->foreignIdFor(UserRole::class)
                ->nullable()
                ->default(null)
                ->constrained('user_roles')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('profile_photo_path', 2048)->nullable()->default(null);
            $table->string('cover_photo_path', 2048)->nullable()->default(null);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
    }
};
