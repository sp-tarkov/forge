<?php

declare(strict_types=1);

use App\Models\AddonVersion;
use App\Models\ModVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_resolved_mod_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(AddonVersion::class)
                ->constrained('addon_versions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignIdFor(ModVersion::class)
                ->constrained('mod_versions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['addon_version_id', 'mod_version_id'], 'unique_addon_mod_version');
            $table->index(['mod_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_resolved_mod_versions');
    }
};
