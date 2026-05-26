<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Description('Downloads assets from Cloudflare R2 to local storage (skips existing files)')]
#[Signature('app:download-r2-assets {--filter="profile-photos/*"} {--chunk=50}')]
final class DownloadR2AssetsCommand extends Command
{
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
            /** @var string $filterPattern */
            $filterPattern = $filter;
            $r2Files = array_filter($r2Files, static function (mixed $file) use ($filterPattern): bool {
                /** @var string $path */
                $path = $file;

                return Str::is($filterPattern, $path);
            });
        }

        // Skip existing files
        $initialCount = count($r2Files);
        $r2Files = array_filter($r2Files, static function (mixed $file): bool {
            /** @var string $path */
            $path = $file;

            return ! Storage::disk('public')->exists($path);
        });
        $skipped = $initialCount - count($r2Files);

        if ($skipped > 0) {
            $this->info(sprintf('Skipping %d existing files', $skipped));
        }

        if ($r2Files === []) {
            $this->info('No files to download.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d files to download', count($r2Files)));

        $progressBar = $this->output->createProgressBar(count($r2Files));
        $progressBar->start();

        $chunkSize = max(1, (int) $this->option('chunk'));
        $allResults = [];

        // Process files in chunks to avoid "too many open files" error
        foreach (array_chunk($r2Files, $chunkSize) as $chunk) {
            // Create download tasks for this chunk
            $tasks = [];
            foreach ($chunk as $file) {
                /** @var string $filePath */
                $filePath = $file;
                $tasks[] = function () use ($filePath): bool {
                    try {
                        // Recreate disk instances inside the closure to avoid serialization
                        $contents = Storage::disk('r2')->get($filePath);

                        if ($contents === null) {
                            return false;
                        }

                        Storage::disk('public')->put($filePath, $contents);

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
