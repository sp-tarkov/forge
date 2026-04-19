<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class, 'owner_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('title', 120);
            $table->string('slug');
            $table->longText('description')->nullable();
            $table->longText('description_html')->nullable();
            $table->string('visibility', 16)->default(ListVisibility::Private->value);
            $table->foreignIdFor(SptVersion::class)
                ->nullable()
                ->constrained('spt_versions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('share_token', 32)->nullable()->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['owner_id', 'visibility']);
            $table->unique(['owner_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_lists');
    }
};
