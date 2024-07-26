<?php

use App\Models\License;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mods', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hub_id')
                ->nullable()
                ->default(null)
                ->unique();
            $table->string('name');
            $table->string('slug');
            $table->string('teaser');
            $table->longText('description');
            $table->string('thumbnail')->default('');
            $table->foreignIdFor(License::class)
                ->nullable()
                ->default(null)
                ->constrained('licenses')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('source_code_link');
            $table->boolean('featured')->default(false);
            $table->boolean('contains_ai_content')->default(false);
            $table->boolean('contains_ads')->default(false);
            $table->boolean('disabled')->default(false);
            $table->softDeletes();
            $table->timestamp('published_at')->default(null);
            $table->timestamps();

            $table->index(['deleted_at', 'disabled'], 'mods_show_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mods');
    }
};
