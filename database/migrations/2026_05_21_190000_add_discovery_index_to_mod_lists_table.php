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
            // Covers the public discovery query (index, search, featured): filter
            // by visibility + is_default + disabled, ordered by updated_at.
            $table->index(
                ['visibility', 'is_default', 'disabled', 'updated_at'],
                'mod_lists_discovery_index',
            );

            // Descriptions are capped at a few thousand characters by validation;
            // longText reserves far more off-page storage than is ever needed.
            $table->text('description')->nullable()->change();
            $table->text('description_html')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mod_lists', function (Blueprint $table): void {
            $table->dropIndex('mod_lists_discovery_index');
            $table->longText('description')->nullable()->change();
            $table->longText('description_html')->nullable()->change();
        });
    }
};
