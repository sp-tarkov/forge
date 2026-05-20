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
            $table->text('custom_ai_disclosure')->nullable()->after('contains_ai_content_locked');
        });

        Schema::table('addons', function (Blueprint $table): void {
            $table->text('custom_ai_disclosure')->nullable()->after('contains_ai_content_locked');
        });
    }

    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table): void {
            $table->dropColumn('custom_ai_disclosure');
        });

        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('custom_ai_disclosure');
        });
    }
};
