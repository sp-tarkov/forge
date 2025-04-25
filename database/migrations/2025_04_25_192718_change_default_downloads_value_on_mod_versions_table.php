<?php

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
        // Add the default value
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('downloads')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the default value
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('downloads')->change();
        });
    }
};
