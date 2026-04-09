<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the new polymorphic additional_authors table
        Schema::create('additional_authors', function (Blueprint $table): void {
            $table->id();
            $table->morphs('authorable');
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            // Unique constraint to prevent duplicate author-resource combinations
            $table->unique(['authorable_type', 'authorable_id', 'user_id'], 'unique_authorable_user');
            // Index on user_id for faster lookups
            $table->index('user_id');
        });

        // Migrate data from mod_additional_authors
        DB::table('mod_additional_authors')->orderBy('id')->chunk(100, function (Collection $authors): void {
            $data = $authors->map(fn (stdClass $author): array => [
                'id' => $author->id,
                'authorable_type' => Mod::class,
                'authorable_id' => $author->mod_id,
                'user_id' => $author->user_id,
                'created_at' => $author->created_at,
                'updated_at' => $author->updated_at,
            ])->all();

            DB::table('additional_authors')->insert($data);
        });

        // Migrate data from addon_additional_authors
        DB::table('addon_additional_authors')->orderBy('id')->chunk(100, function (Collection $authors): void {
            $data = $authors->map(fn (stdClass $author): array => [
                'id' => $author->id,
                'authorable_type' => Addon::class,
                'authorable_id' => $author->addon_id,
                'user_id' => $author->user_id,
                'created_at' => $author->created_at,
                'updated_at' => $author->updated_at,
            ])->all();

            DB::table('additional_authors')->insert($data);
        });

        // Drop the old tables
        Schema::dropIfExists('mod_additional_authors');
        Schema::dropIfExists('addon_additional_authors');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate mod_additional_authors table
        Schema::create('mod_additional_authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mod_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['mod_id', 'user_id']);
            $table->index('user_id');
        });

        // Recreate addon_additional_authors table
        Schema::create('addon_additional_authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['addon_id', 'user_id']);
            $table->index('user_id');
        });

        // Migrate data back from additional_authors to mod_additional_authors
        DB::table('additional_authors')
            ->where('authorable_type', Mod::class)
            ->orderBy('id')
            ->chunk(100, function (Collection $authors): void {
                $data = $authors->map(fn (stdClass $author): array => [
                    'id' => $author->id,
                    'mod_id' => $author->authorable_id,
                    'user_id' => $author->user_id,
                    'created_at' => $author->created_at,
                    'updated_at' => $author->updated_at,
                ])->all();

                DB::table('mod_additional_authors')->insert($data);
            });

        // Migrate data back from additional_authors to addon_additional_authors
        DB::table('additional_authors')
            ->where('authorable_type', Addon::class)
            ->orderBy('id')
            ->chunk(100, function (Collection $authors): void {
                $data = $authors->map(fn (stdClass $author): array => [
                    'id' => $author->id,
                    'addon_id' => $author->authorable_id,
                    'user_id' => $author->user_id,
                    'created_at' => $author->created_at,
                    'updated_at' => $author->updated_at,
                ])->all();

                DB::table('addon_additional_authors')->insert($data);
            });

        // Drop the polymorphic table
        Schema::dropIfExists('additional_authors');
    }
};
