<?php

use App\Models\Mod;
use App\Models\SptVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_versions', function (Blueprint $table) {
            $table->id();
            $table->string('hub_id')->nullable()->unique()->default(null);
            $table->foreignIdFor(Mod::class)->constrained('mods');
            $table->string('version');
            $table->longText('description');
            $table->string('link');
            $table->foreignIdFor(SptVersion::class)->constrained('spt_versions');
            $table->string('virus_total_link');
            $table->unsignedBigInteger('downloads');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_versions');
    }
};
