<?php

declare(strict_types=1);

namespace App\Enums\Api\V0;

/**
 * The granularity of an aggregated API usage row.
 *
 * Per-minute rows are the fine-grained buckets flushed from Redis every minute and kept for a short window. Daily rows
 * are coarser rollups derived from the minute rows and kept for long-term trend analysis.
 */
enum ApiUsagePeriod: string
{
    case Minute = 'minute';
    case Day = 'day';
}
