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
        Schema::table('spt_versions', function (Blueprint $table) {
            $table->dropColumn('hub_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spt_versions', function (Blueprint $table) {
            $table->bigInteger('hub_id')
                ->nullable()
                ->default(null)
                ->unique()
                ->after('id');
        });
    }
};
