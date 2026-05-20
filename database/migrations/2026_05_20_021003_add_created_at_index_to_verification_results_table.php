<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an index on created_at so the admin file verification listing can satisfy
     * its "order by created_at desc" from the index instead of a full filesort.
     * Without this index MySQL sorts every wide row in memory and can exhaust
     * sort_buffer_size on large result sets.
     */
    public function up(): void
    {
        Schema::table('verification_results', function (Blueprint $table): void {
            $table->index('created_at', 'verification_results_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_results', function (Blueprint $table): void {
            $table->dropIndex('verification_results_created_at_index');
        });
    }
};
