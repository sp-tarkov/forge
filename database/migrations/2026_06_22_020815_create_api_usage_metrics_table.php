<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregated API usage metrics.
 *
 * One row per (period, period_start, route_name, method, status_code). Rows are flushed from Redis counters every
 * minute (`period = minute`) and rolled up into coarser daily rows (`period = day`) for long-term retention. The
 * `lat_b0`..`lat_b9` columns are a latency histogram (see App\Enums\Api\V0\ApiLatencyBucket) used to estimate p95
 * without storing per-request timings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('period', 10);
            $table->dateTime('period_start');
            $table->string('route_name', 100);
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedBigInteger('request_count')->default(0);
            $table->unsignedBigInteger('latency_sum_ms')->default(0);

            // Latency histogram bucket counts. Boundaries are defined once in ApiLatencyBucket.
            foreach (range(0, 9) as $bucket) {
                $table->unsignedBigInteger('lat_b'.$bucket)->default(0);
            }

            $table->timestamps();

            // Drives idempotent upserts during rollup, and serves the dashboard's (period, period_start) range scans
            // via its leading columns.
            $table->unique(
                ['period', 'period_start', 'route_name', 'method', 'status_code'],
                'api_usage_metrics_dimension_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_metrics');
    }
};
