<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Timeout(60)]
final class ArtisanCallJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [1, 5, 10];

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(public string $command, public array $arguments = []) {}

    public function handle(): void
    {
        Artisan::call($this->command, $this->arguments);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ArtisanCallJob failed', [
            'command' => $this->command,
            'arguments' => $this->arguments,
            'error' => $exception?->getMessage(),
        ]);
    }
}
