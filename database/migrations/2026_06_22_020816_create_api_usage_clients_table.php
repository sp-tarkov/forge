<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heaviest API clients per period.
 *
 * Only the top-N IPs per bucket are persisted (see config `api.usage.top_clients`); everything below that threshold
 * is discarded at rollup time. Used by the admin dashboard to surface the heaviest callers for abuse investigation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('period', 10);
            $table->dateTime('period_start');
            $table->string('ip', 45);
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamps();

            // Drives idempotent upserts during rollup and serves (period, period_start) range scans.
            $table->unique(['period', 'period_start', 'ip'], 'api_usage_clients_dimension_unique');

            // Orders the heaviest callers within a single bucket.
            $table->index(['period', 'period_start', 'request_count'], 'api_usage_clients_ranking_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_clients');
    }
};
