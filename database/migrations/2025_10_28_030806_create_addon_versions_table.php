<?php

declare(strict_types=1);

use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Addon::class)
                ->constrained('addons')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('version');
            $table->unsignedInteger('version_major')->default(0);
            $table->unsignedInteger('version_minor')->default(0);
            $table->unsignedInteger('version_patch')->default(0);
            $table->string('version_pre_release')->default('');
            $table->longText('description')->nullable();
            $table->string('link', 500);
            $table->unsignedBigInteger('content_length')->nullable();
            $table->string('mod_version_constraint');
            $table->string('virus_total_link', 500)->nullable();
            $table->unsignedBigInteger('downloads')->default(0);
            $table->boolean('disabled')->default(false);
            $table->boolean('discord_notification_sent')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['addon_id']);
            $table->index(['version']);
            $table->index(['addon_id', 'disabled', 'published_at'], 'addon_versions_filtering_index');
            $table->index(['version_major', 'version_minor', 'version_patch', 'version_pre_release'], 'addon_versions_version_components_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_versions');
    }
};
