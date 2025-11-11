<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class UpdateGeoLiteDatabase extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'geoip:update {--force : Force update even if database is recent}';

    /**
     * The console command description.
     */
    protected $description = 'Download and update the MaxMind GeoLite2-City database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting GeoLite2 database update...');

        // Run system diagnostics first
        if (! $this->runSystemDiagnostics()) {
            return self::FAILURE;
        }

        // Check if credentials are configured
        $accountId = config('services.maxmind.account_id');
        $licenseKey = config('services.maxmind.license_key');

        if (! $accountId || ! $licenseKey) {
            $message = 'MaxMind credentials not configured. Please set MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY environment variables.';
            $this->error($message);
            Log::error('GeoLite2 database update failed: Missing credentials');

            return self::FAILURE;
        }

        $databasePath = storage_path('app/geoip/GeoLite2-City.mmdb');
        $tempDir = storage_path('app/geoip/temp');
        $downloadUrl = 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz';

        // Check if an update is needed
        if (! $this->option('force') && $this->isDatabaseRecent($databasePath)) {
            $this->info('Database is recent. Use --force to update anyway.');

            return self::SUCCESS;
        }

        try {
            // Create directories with better error handling
            $this->ensureDirectoriesExist($databasePath, $tempDir);

            // Test network connectivity first
            $this->testNetworkConnectivity($downloadUrl, $accountId, $licenseKey);

            // Download the database
            $this->info('Downloading GeoLite2-City database...');
            $tempFile = $tempDir.'/GeoLite2-City.tar.gz';

            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->timeout(300)
                ->get($downloadUrl);

            throw_unless($response->successful(),
                Exception::class,
                sprintf(
                    'Failed to download database: HTTP %d - %s',
                    $response->status(),
                    $response->body()
                ));

            $downloadSize = mb_strlen($response->body());
            $this->info(sprintf('Downloaded %s bytes', number_format($downloadSize)));

            File::put($tempFile, $response->body());
            $this->info('Download completed.');

            // Extract the database with better error handling
            $this->info('Extracting database...');
            $this->extractDatabase($tempDir, $tempFile);

            // Find and move the .mmdb file
            $extractedDatabase = $this->findExtractedDatabase($tempDir);

            // Back up existing database
            $this->backupExistingDatabase($databasePath);

            // Move the new database into place
            File::move($extractedDatabase, $databasePath);
            $fileSize = File::size($databasePath);
            $this->info(sprintf('Database updated successfully: %s (%s bytes)', $databasePath, number_format($fileSize)));

            // Clean up temporary files
            File::deleteDirectory($tempDir);
            $this->info('Temporary files cleaned up.');

            // Log the update
            Log::info('GeoLite2 database updated successfully', [
                'database_path' => $databasePath,
                'file_size' => $fileSize,
                'download_size' => $downloadSize,
                'updated_at' => now()->toISOString(),
            ]);

            $this->info('GeoLite2 database update completed successfully!');

            return self::SUCCESS;

        } catch (Exception $exception) {
            $this->error('Failed to update GeoLite2 database: '.$exception->getMessage());

            Log::error('GeoLite2 database update failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'system_info' => $this->getSystemInfo(),
            ]);

            // Clean up on failure
            $this->cleanupOnFailure($tempDir, $databasePath);

            return self::FAILURE;
        }
    }

    /**
     * Check if the database file is recent (less than 7 days old).
     */
    private function isDatabaseRecent(string $databasePath): bool
    {
        if (! File::exists($databasePath)) {
            return false;
        }

        $fileModified = File::lastModified($databasePath);
        $daysSinceModified = (time() - $fileModified) / 86400; // Convert to days

        return $daysSinceModified < 7;
    }

    /**
     * Run system diagnostics to check prerequisites.
     */
    private function runSystemDiagnostics(): bool
    {
        $this->info('Running system diagnostics...');

        $issues = [];

        // Check if tar command is available
        $tarResult = Process::run('which tar');
        if (! $tarResult->successful()) {
            $issues[] = 'tar command not found in PATH';
        } else {
            $this->info('✓ tar command available: '.mb_trim($tarResult->output()));
        }

        // Check PHP version and memory limit
        $memoryLimit = ini_get('memory_limit');
        $this->info('✓ PHP memory limit: '.$memoryLimit);

        if ($this->isMemoryLimitTooLow($memoryLimit)) {
            $issues[] = 'PHP memory limit may be too low for processing large files';
        }

        // Check available disk space
        $storageSpace = disk_free_space(storage_path());
        if ($storageSpace !== false) {
            $this->info('✓ Available storage: '.number_format($storageSpace / 1024 / 1024).' MB');

            if ($storageSpace < 200 * 1024 * 1024) { // Less than 200MB
                $issues[] = 'Low disk space in storage directory (less than 200MB available)';
            }
        } else {
            $issues[] = 'Unable to check available disk space';
        }

        // Report issues
        if (! empty($issues)) {
            $this->error('System diagnostic issues found:');
            foreach ($issues as $issue) {
                $this->error('  • '.$issue);
            }

            Log::warning('GeoLite2 update system diagnostic issues', ['issues' => $issues]);

            return false;
        }

        $this->info('✓ System diagnostics passed');

        return true;
    }

    /**
     * Check if memory limit is too low.
     */
    private function isMemoryLimitTooLow(string $memoryLimit): bool
    {
        if ($memoryLimit === '-1') {
            return false; // No limit
        }

        $bytes = $this->convertToBytes($memoryLimit);

        return $bytes < 128 * 1024 * 1024; // Less than 128MB
    }

    /**
     * Convert memory limit string to bytes.
     */
    private function convertToBytes(string $value): int
    {
        $value = mb_trim($value);
        $last = mb_strtolower($value[mb_strlen($value) - 1]);
        $number = (int) $value;

        return match ($last) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Ensure required directories exist with proper permissions.
     */
    private function ensureDirectoriesExist(string $databasePath, string $tempDir): void
    {
        $directories = [
            'database' => dirname($databasePath),
            'temp' => $tempDir,
        ];

        foreach ($directories as $name => $dir) {
            try {
                File::ensureDirectoryExists($dir);

                throw_unless(is_writable($dir), Exception::class, sprintf('%s directory is not writable: %s', $name, $dir));

                $this->info(sprintf('✓ %s directory ready: %s', $name, $dir));
            } catch (Exception $e) {
                throw new Exception(sprintf('Failed to create %s directory: %s. Error: %s', $name, $dir, $e->getMessage()));
            }
        }
    }

    /**
     * Test network connectivity to MaxMind servers.
     */
    private function testNetworkConnectivity(string $url, string $accountId, string $licenseKey): void
    {
        $this->info('Testing network connectivity to MaxMind...');

        try {
            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->timeout(30)
                ->head($url);

            throw_unless($response->successful(),
                Exception::class,
                sprintf(
                    'Network connectivity test failed: HTTP %d - %s',
                    $response->status(),
                    $response->body()
                ));

            $this->info('✓ Network connectivity test passed');
        } catch (Exception $exception) {
            throw new Exception('Network connectivity test failed: '.$exception->getMessage());
        }
    }

    /**
     * Extract database with enhanced error handling.
     */
    private function extractDatabase(string $tempDir, string $tempFile): void
    {
        $command = sprintf('cd %s && tar -xzf %s', escapeshellarg($tempDir), escapeshellarg(basename($tempFile)));
        $result = Process::run($command);

        if (! $result->successful()) {
            $errorOutput = $result->errorOutput() ?: $result->output();
            throw new Exception('Failed to extract database: '.$errorOutput);
        }

        $this->info('✓ Database extraction completed');
    }

    /**
     * Find the extracted database file.
     */
    private function findExtractedDatabase(string $tempDir): string
    {
        $extractedFiles = File::glob($tempDir.'/GeoLite2-City_*/GeoLite2-City.mmdb');

        if (empty($extractedFiles)) {
            // List contents for debugging
            $contents = File::glob($tempDir.'/*');
            $contentsInfo = empty($contents) ? 'directory is empty' : 'found: '.implode(', ', array_map(basename(...), $contents));

            throw new Exception('Could not find GeoLite2-City.mmdb in extracted files. Directory contents: '.$contentsInfo);
        }

        $extractedDatabase = $extractedFiles[0];
        $fileSize = File::size($extractedDatabase);

        $this->info(sprintf('✓ Found extracted database: %s (', $extractedDatabase).number_format($fileSize).' bytes)');

        return $extractedDatabase;
    }

    /**
     * Backup existing database.
     */
    private function backupExistingDatabase(string $databasePath): void
    {
        if (File::exists($databasePath)) {
            $backupPath = $databasePath.'.backup';

            try {
                File::move($databasePath, $backupPath);
                $this->info('✓ Existing database backed up to: '.$backupPath);
            } catch (Exception $e) {
                throw new Exception('Failed to backup existing database: '.$e->getMessage());
            }
        }
    }

    /**
     * Clean up on failure and attempt to restore backup.
     */
    private function cleanupOnFailure(string $tempDir, string $databasePath): void
    {
        // Clean up temporary files
        if (File::exists($tempDir)) {
            try {
                File::deleteDirectory($tempDir);
                $this->info('Cleaned up temporary files');
            } catch (Exception $e) {
                $this->warn('Failed to clean up temporary files: '.$e->getMessage());
            }
        }

        // Restore backup if exists and main database is missing
        $backupPath = $databasePath.'.backup';
        if (File::exists($backupPath) && ! File::exists($databasePath)) {
            try {
                File::move($backupPath, $databasePath);
                $this->info('✓ Restored previous database from backup');
            } catch (Exception $e) {
                $this->error('Failed to restore backup database: '.$e->getMessage());
            }
        }
    }

    /**
     * Get system information for debugging.
     *
     * @return array<string, mixed>
     */
    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'storage_path' => storage_path(),
            'available_disk_space' => disk_free_space(storage_path()),
            'operating_system' => php_uname(),
            'current_user' => get_current_user(),
        ];
    }
}
