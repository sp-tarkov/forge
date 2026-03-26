<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;

class ArtisanCallJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(public string $command, public array $arguments = []) {}

    public function handle(): void
    {
        Artisan::call($this->command, $this->arguments);
    }
}
