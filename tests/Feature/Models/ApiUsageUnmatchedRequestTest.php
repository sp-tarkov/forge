<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageUnmatchedRequest;
use Carbon\CarbonImmutable;

it('casts its attributes', function (): void {
    $unmatched = ApiUsageUnmatchedRequest::factory()->create();

    expect($unmatched->period)->toBeInstanceOf(ApiUsagePeriod::class)
        ->and($unmatched->period_start)->toBeInstanceOf(CarbonImmutable::class)
        ->and($unmatched->status_code)->toBeInt()
        ->and($unmatched->request_count)->toBeInt();
});
