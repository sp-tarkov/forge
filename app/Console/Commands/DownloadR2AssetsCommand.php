<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadR2AssetsCommand extends Command
{
    protected $signature = 'app:download-r2-assets
                            {--filter= : Only download files matching this pattern (e.g., "profile-photos/*")}
                            {--chunk=50 : Number of files to process concurrently per batch}';

    protected $description = 'Downloads assets from Cloudflare R2 to local storage (skips existing files)';

    /**
     * This command downloads all files from Cloudflare R2 to the local public disk.
     * Useful for development environments where you want to work with production assets locally.
     */
    public function handle(): int
    {
        $this->info('Starting R2 asset download...');

        $r2Files = Storage::disk('r2')->allFiles();

        if (empty($r2Files)) {
            $this->warn('No files found in R2 storage.');

            return self::SUCCESS;
        }

        // Apply filter if specified
        if ($filter = $this->option('filter')) {
            $r2Files = array_filter($r2Files, fn (string $file): bool => Str::is($filter, $file));
        }

        // Skip existing files
        $initialCount = count($r2Files);
        $r2Files = array_filter($r2Files, fn (string $file): bool => ! Storage::disk('public')->exists($file));
        $skipped = $initialCount - count($r2Files);

        if ($skipped > 0) {
            $this->info(sprintf('Skipping %d existing files', $skipped));
        }

        if (empty($r2Files)) {
            $this->info('No files to download.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d files to download', count($r2Files)));

        $progressBar = $this->output->createProgressBar(count($r2Files));
        $progressBar->start();

        $chunkSize = (int) $this->option('chunk');
        $allResults = [];

        // Process files in chunks to avoid "too many open files" error
        foreach (array_chunk($r2Files, $chunkSize) as $chunk) {
            // Create download tasks for this chunk
            $tasks = [];
            foreach ($chunk as $file) {
                $tasks[] = function () use ($file): bool {
                    try {
                        // Recreate disk instances inside the closure to avoid serialization
                        $contents = Storage::disk('r2')->get($file);

                        if ($contents === null) {
                            return false;
                        }

                        Storage::disk('public')->put($file, $contents);

                        return true;
                    } catch (Exception) {
                        return false;
                    }
                };
            }

            // Execute this chunk's downloads concurrently
            $results = Concurrency::run($tasks);
            $allResults = array_merge($allResults, $results);

            // Update progress bar after each chunk
            foreach ($results as $result) {
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $downloaded = count(array_filter($allResults));
        $failed = count($allResults) - $downloaded;

        $this->info(sprintf('Download complete! Downloaded: %d, Failed: %d', $downloaded, $failed));

        if ($failed > 0) {
            $this->warn('Some files failed to download. Check your R2 credentials and network connection.');
        }

        return self::SUCCESS;
    }
}
