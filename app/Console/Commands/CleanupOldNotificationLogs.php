<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationLog;
use Illuminate\Console\Command;

class CleanupOldNotificationLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:cleanup-logs {--days=30 : Number of days to retain logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old notification logs to prevent table bloat';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info(sprintf('Cleaning up notification logs older than %d days...', $days));

        $deletedCount = NotificationLog::query()->where('created_at', '<', $cutoffDate)->delete();

        if ($deletedCount > 0) {
            $this->info(sprintf('Successfully deleted %s old notification logs.', $deletedCount));
        } else {
            $this->info('No old notification logs found to delete.');
        }

        return Command::SUCCESS;
    }
}
