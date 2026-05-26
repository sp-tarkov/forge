<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

#[Description('Send a heartbeat ping to the configured URL')]
#[Signature('app:forge-heartbeat')]
final class ForgeHeartbeat extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = config()->string('app.forge_heartbeat_url', '');

        if ($url === '') {
            $this->error('No URL configured. Please set FORGE_HEARTBEAT_URL in your .env file.');

            return Command::FAILURE;
        }

        try {
            $response = Http::connectTimeout(5)->timeout(30)->get($url);

            if ($response->successful()) {
                $message = sprintf('Successfully pinged %s - Status: %d', $url, $response->status());
                $this->info($message);
                Log::info($message);
            } else {
                $message = sprintf('Failed to ping %s - Status: %d', $url, $response->status());
                $this->error($message);
                Log::warning($message);
            }

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $message = sprintf('Error pinging %s: %s', $url, $exception->getMessage());
            $this->error($message);
            Log::error($message);

            return Command::FAILURE;
        }
    }
}
