<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mods', function (Blueprint $table): void {
            $table->boolean('addons_disabled')->default(false)->after('comments_disabled');
        });
    }

    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table): void {
            $table->dropColumn('addons_disabled');
        });
    }
};
