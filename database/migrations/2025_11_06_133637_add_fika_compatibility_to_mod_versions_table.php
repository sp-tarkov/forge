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
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->enum('fika_compatibility', ['compatible', 'incompatible', 'unknown'])
                ->default('unknown')
                ->nullable(false)
                ->after('disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_versions', function (Blueprint $table) {
            $table->dropColumn('fika_compatibility');
        });
    }
};
