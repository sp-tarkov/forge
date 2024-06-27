<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spt_versions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('hub_id')->nullable()->default(null)->unique();
            $table->string('version');
            $table->string('color_class');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['version', 'deleted_at'], 'spt_versions_filtering_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spt_versions');
    }
};
