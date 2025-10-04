<?php

declare(strict_types=1);

use App\Models\ModAddon;
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
        Schema::create('mod_addon_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ModAddon::class)
                ->constrained('mod_addons')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('version');
            $table->unsignedInteger('version_major')->default(0);
            $table->unsignedInteger('version_minor')->default(0);
            $table->unsignedInteger('version_patch')->default(0);
            $table->string('version_pre_release')->default('');
            $table->longText('description');
            $table->string('link');
            $table->string('spt_version_constraint');
            $table->string('virus_total_link');
            $table->unsignedBigInteger('downloads');
            $table->boolean('disabled')->default(false);
            $table->timestamp('published_at')->nullable()->default(null);
            $table->timestamps();

            $table->index(['version']);
            $table->index(['mod_addon_id']);
            $table->index(['disabled']);
            $table->index(['published_at']);
            $table->index(['version_major', 'version_minor', 'version_patch', 'version_pre_release'], 'mod_addon_versions_version_components_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_addon_versions');
    }
};
