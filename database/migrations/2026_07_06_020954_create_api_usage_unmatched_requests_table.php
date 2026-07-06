<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests to the v0 API surface that did not match any registered route.
 *
 * One row per (period, period_start, path, method, status_code). Only the top-N paths per bucket are persisted (see
 * config `api.usage.top_unmatched`) so scanner noise cannot grow the table without bound. Used by the admin dashboard
 * to show which nonexistent endpoints callers are hitting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_usage_unmatched_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('period', 10);
            $table->dateTime('period_start');
            $table->string('path', 191);
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamps();

            // Drives idempotent upserts during rollup and serves (period, period_start) range scans.
            $table->unique(
                ['period', 'period_start', 'path', 'method', 'status_code'],
                'api_usage_unmatched_dimension_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_usage_unmatched_requests');
    }
};
