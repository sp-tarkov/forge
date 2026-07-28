<?php

declare(strict_types=1);

use App\Jobs\VerificationSweepJob;
use Illuminate\Support\Facades\Bus;

describe('Verification Sweep Command', function (): void {
    test('queues the verification sweep job', function (): void {
        Bus::fake();

        $this->artisan('app:verification-sweep')
            ->expectsOutputToContain('VerificationSweepJob has been added to the queue')
            ->assertSuccessful();

        Bus::assertDispatched(VerificationSweepJob::class);
    });
});
