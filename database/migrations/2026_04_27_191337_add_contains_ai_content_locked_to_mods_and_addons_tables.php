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
            $table->boolean('contains_ai_content_locked')->default(false)->after('contains_ai_content');
        });

        Schema::table('addons', function (Blueprint $table): void {
            $table->boolean('contains_ai_content_locked')->default(false)->after('contains_ai_content');
        });
    }

    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table): void {
            $table->dropColumn('contains_ai_content_locked');
        });

        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('contains_ai_content_locked');
        });
    }
};
