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
            $table->string('thumbnail', 500)->nullable()->after('description_html');
            $table->string('thumbnail_hash')->nullable()->after('thumbnail');
        });
    }

    public function down(): void
    {
        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->dropColumn(['thumbnail', 'thumbnail_hash']);
        });
    }
};
