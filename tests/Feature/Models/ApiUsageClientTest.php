<?php

declare(strict_types=1);

use App\Enums\Api\V0\ApiUsagePeriod;
use App\Models\ApiUsageClient;
use Carbon\CarbonImmutable;

it('casts its attributes', function (): void {
    $client = ApiUsageClient::factory()->create();

    expect($client->period)->toBeInstanceOf(ApiUsagePeriod::class)
        ->and($client->period_start)->toBeInstanceOf(CarbonImmutable::class)
        ->and($client->request_count)->toBeInt();
});
