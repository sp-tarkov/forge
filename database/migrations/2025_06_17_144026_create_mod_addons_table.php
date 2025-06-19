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
        Schema::create('mod_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('teaser');
            $table->longText('description');
            $table->string('thumbnail')->default('');
            $table->foreignIdFor('licenses')
                ->nullable()
                ->default(null)
                ->constrained('licenses')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedBigInteger('downloads')->default(0);
            $table->string('source_code_url');
            $table->boolean('disabled')->default(false);
            $table->boolean('featured')->default(false);
            $table->boolean('contains_ai_content')->default(false);
            $table->boolean('contains_ads')->default(false);
            $table->timestamp('published_at')->nullable()->default(null);
            $table->timestamps();

            $table->index('name');
            $table->index(['slug']);
            $table->index(['featured']);
            $table->index('contains_ads');
            $table->index('contains_ai_content');
            $table->index('created_at');
            $table->index('updated_at');
            $table->index('published_at');
            $table->index('disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_addons');
    }
};
