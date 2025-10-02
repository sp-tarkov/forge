<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForgeHeartbeat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:forge-heartbeat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a heartbeat ping to the configured URL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $url = config('app.forge_heartbeat_url');

        if (! $url) {
            $this->error('No URL configured. Please set FORGE_HEARTBEAT_URL in your .env file.');

            return Command::FAILURE;
        }

        try {
            $response = Http::timeout(30)->get($url);

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
