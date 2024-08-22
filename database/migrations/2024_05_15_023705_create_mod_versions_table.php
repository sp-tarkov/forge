<?php

use App\Models\Mod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_versions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hub_id')
                ->nullable()
                ->default(null)
                ->unique();
            $table->foreignIdFor(Mod::class)
                ->constrained('mods')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('version');
            $table->longText('description');
            $table->string('link');
            $table->string('spt_version_constraint');
            $table->foreignId('resolved_spt_version_id')
                ->nullable()
                ->constrained('spt_versions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('virus_total_link');
            $table->unsignedBigInteger('downloads');
            $table->boolean('disabled')->default(false);
            $table->softDeletes();
            $table->timestamp('published_at')->nullable()->default(null);
            $table->timestamps();

            $table->index(['mod_id', 'deleted_at', 'disabled', 'version'], 'mod_versions_filtering_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_versions');
    }
};
