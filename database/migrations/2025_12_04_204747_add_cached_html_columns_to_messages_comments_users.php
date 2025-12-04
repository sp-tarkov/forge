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
        Schema::table('messages', function (Blueprint $table): void {
            $table->longText('content_html')->nullable()->after('content');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->longText('body_html')->nullable()->after('body');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->longText('about_html')->nullable()->after('about');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropColumn('content_html');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('body_html');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('about_html');
        });
    }
};
