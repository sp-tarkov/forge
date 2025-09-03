<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Visitor;
use Illuminate\Console\Command;

class CleanOldVisitorRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'visitors:clean {--hours=6 : Number of hours to keep visitor records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old visitor records from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $this->info(sprintf('Cleaning visitor records older than %d hours...', $hours));

        $deleted = Visitor::cleanOldRecords($hours);

        $this->info(sprintf('Deleted %d old visitor records.', $deleted));

        return Command::SUCCESS;
    }
}
