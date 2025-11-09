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
        // Change the guid column to use case-sensitive collation
        Schema::table('mods', function (Blueprint $table) {
            $table->string('guid')->charset('utf8mb4')->collation('utf8mb4_0900_as_cs')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the default case-insensitive collation
        Schema::table('mods', function (Blueprint $table) {
            $table->string('guid')->charset('utf8mb4')->collation('utf8mb4_0900_ai_ci')->change();
        });
    }
};
