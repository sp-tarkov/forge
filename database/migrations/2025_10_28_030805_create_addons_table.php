<?php

declare(strict_types=1);

use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Mod::class)
                ->nullable()
                ->constrained('mods')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignIdFor(User::class, 'owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('name');
            $table->string('slug');
            $table->string('teaser')->nullable();
            $table->longText('description')->nullable();
            $table->string('thumbnail', 500)->nullable();
            $table->string('thumbnail_hash')->nullable();
            $table->foreignIdFor(License::class)
                ->nullable()
                ->constrained('licenses')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedBigInteger('downloads')->default(0);
            $table->boolean('disabled')->default(false);
            $table->boolean('contains_ai_content')->default(false);
            $table->boolean('contains_ads')->default(false);
            $table->boolean('comments_disabled')->default(false);
            $table->timestamp('detached_at')->nullable();
            $table->foreignId('detached_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->boolean('discord_notification_sent')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['mod_id']);
            $table->index(['owner_id']);
            $table->index(['slug']);
            $table->index(['disabled', 'published_at'], 'addons_filtering_index');
            $table->index(['detached_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
