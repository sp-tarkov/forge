<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Addon::class)
                ->constrained('addons')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignIdFor(User::class)
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['addon_id', 'user_id'], 'unique_addon_user');
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_authors');
    }
};
