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

        // Check if credentials are configured
        $accountId = config('services.maxmind.account_id');
        $licenseKey = config('services.maxmind.license_key');

        if (! $accountId || ! $licenseKey) {
            $this->error('MaxMind credentials not configured. Please set MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY environment variables.');

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
            // Create directories
            File::ensureDirectoryExists(dirname($databasePath));
            File::ensureDirectoryExists($tempDir);

            // Download the database
            $this->info('Downloading GeoLite2-City database...');
            $tempFile = $tempDir.'/GeoLite2-City.tar.gz';

            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->timeout(300)
                ->get($downloadUrl);

            throw_unless($response->successful(), new Exception('Failed to download database: HTTP '.$response->status()));

            File::put($tempFile, $response->body());
            $this->info('Download completed.');

            // Extract the database
            $this->info('Extracting database...');
            $result = Process::run(sprintf('cd %s && tar -xzf GeoLite2-City.tar.gz', $tempDir));

            throw_unless($result->successful(), new Exception('Failed to extract database: '.$result->errorOutput()));

            // Find and move the .mmdb file
            $extractedFiles = File::glob($tempDir.'/GeoLite2-City_*/GeoLite2-City.mmdb');

            throw_if(empty($extractedFiles), new Exception('Could not find GeoLite2-City.mmdb in extracted files'));

            $extractedDatabase = $extractedFiles[0];

            // Back up the existing database if it exists
            if (File::exists($databasePath)) {
                $backupPath = $databasePath.'.backup';
                File::move($databasePath, $backupPath);
                $this->info('Existing database backed up to: '.$backupPath);
            }

            // Move the new database into place
            File::move($extractedDatabase, $databasePath);
            $this->info('Database updated successfully: '.$databasePath);

            // Clean up temporary files
            File::deleteDirectory($tempDir);
            $this->info('Temporary files cleaned up.');

            // Log the update
            Log::info('GeoLite2 database updated successfully', [
                'database_path' => $databasePath,
                'file_size' => File::size($databasePath),
                'updated_at' => now()->toISOString(),
            ]);

            $this->info('GeoLite2 database update completed successfully!');

            return self::SUCCESS;

        } catch (Exception $exception) {
            $this->error('Failed to update GeoLite2 database: '.$exception->getMessage());

            Log::error('GeoLite2 database update failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Clean up on failure
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            // Restore backup if exists
            $backupPath = $databasePath.'.backup';
            if (File::exists($backupPath) && ! File::exists($databasePath)) {
                File::move($backupPath, $databasePath);
                $this->info('Restored previous database from backup.');
            }

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
}
