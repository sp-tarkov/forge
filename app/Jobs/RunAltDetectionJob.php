<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AltInvestigationRun;
use App\Models\User;
use App\Services\AltDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

/**
 * Runs an alt-detection investigation for a suspect on the dedicated alt-detection queue and stores the result on its
 * AltInvestigationRun so the admin page can poll for and cache the outcome.
 */
#[Timeout(300)]
#[Tries(1)]
final class RunAltDetectionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AltInvestigationRun $run,
    ) {
        $this->onQueue(config()->string('alt-detection.queue', 'alt-detection'));
    }

    public function handle(AltDetectionService $service): void
    {
        $suspect = User::query()->find($this->run->user_id);

        if (! $suspect instanceof User) {
            $this->run->markFailed('The suspect account no longer exists.');

            return;
        }

        $this->run->markProcessing();

        $this->run->markCompleted($service->investigate($suspect));
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $this->run->markFailed($exception?->getMessage() ?? 'The investigation job failed.');
    }
}
