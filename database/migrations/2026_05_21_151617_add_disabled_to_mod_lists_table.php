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
        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->boolean('disabled')->default(false)->after('comments_disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->dropColumn('disabled');
        });
    }
};
