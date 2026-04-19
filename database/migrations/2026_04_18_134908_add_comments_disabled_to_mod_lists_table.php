<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->boolean('comments_disabled')->default(false)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->dropColumn('comments_disabled');
        });
    }
};
