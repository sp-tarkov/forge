<?php

declare(strict_types=1);

use App\Jobs\DetectDownloadChangesJob;
use Illuminate\Support\Facades\Bus;

describe('Detect Download Changes Command', function (): void {
    test('queues the change detection job', function (): void {
        Bus::fake();

        $this->artisan('app:detect-download-changes')
            ->expectsOutputToContain('DetectDownloadChangesJob has been added to the queue')
            ->assertSuccessful();

        Bus::assertDispatched(DetectDownloadChangesJob::class);
    });
});
