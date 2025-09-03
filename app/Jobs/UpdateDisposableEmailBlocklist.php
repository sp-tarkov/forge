<?php

declare(strict_types=1);

namespace App\Jobs;

use Exception;
use App\Models\DisposableEmailBlocklist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UpdateDisposableEmailBlocklist implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Download the latest blocklist
            $response = Http::timeout(60)->get('https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf');

            if (! $response->successful()) {
                Log::error('Failed to download disposable email blocklist', ['status' => $response->status()]);

                return;
            }

            $content = $response->body();

            // Store the file locally
            Storage::put('blocklists/disposable_email_blocklist.conf', $content);

            // Parse the domains
            $lines = explode("\n", $content);
            $domains = [];

            foreach ($lines as $line) {
                $domain = trim($line);
                if ($domain !== '' && ! str_starts_with($domain, '#')) {
                    $domains[] = $domain;
                }
            }

            // Update the database in chunks to avoid memory issues
            DB::transaction(function () use ($domains): void {
                // Delete existing entries (truncate doesn't work in transactions)
                DB::table('disposable_email_blocklist')->delete();

                // Insert new domains in chunks
                $chunks = array_chunk($domains, 1000);
                foreach ($chunks as $chunk) {
                    $records = array_map(fn($domain): array => [
                        'domain' => $domain,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $chunk);

                    DisposableEmailBlocklist::query()->insert($records);
                }
            });

            // Clear the cache
            DisposableEmailBlocklist::clearAllCaches();

            Log::info('Successfully updated disposable email blocklist', ['count' => count($domains)]);
        } catch (Exception $exception) {
            Log::error('Error updating disposable email blocklist', ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
