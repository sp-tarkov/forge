<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the verification_results table to store file verification pipeline results.
     * Each record represents a single verification run for a ModVersion or AddonVersion.
     */
    public function up(): void
    {
        Schema::create('verification_results', function (Blueprint $table): void {
            $table->id();
            $table->morphs('verifiable');
            $table->string('status');
            $table->string('trigger');
            $table->string('download_url');
            $table->boolean('download_ok')->nullable();
            $table->unsignedBigInteger('downloaded_size')->nullable();
            $table->string('downloaded_sha256')->nullable();
            $table->boolean('archive_ok')->nullable();
            $table->json('file_tree')->nullable();
            $table->json('details')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['verifiable_type', 'verifiable_id', 'created_at'], 'verification_results_lookup_index');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_results');
    }
};
