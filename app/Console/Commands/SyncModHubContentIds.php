<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncModHubContentIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mods:sync-hub-content-ids
                            {--chunk=100 : Number of records to process at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync hub_content_id values from hub database for all mods with hub_id';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');

        $this->info('Starting hub_content_id sync...');
        $this->info('Chunk size: '.$chunkSize);
        $this->newLine();

        $totalMods = Mod::query()->whereNotNull('hub_id')->count();
        $processedCount = 0;
        $updatedCount = 0;
        $notFoundCount = 0;

        Mod::query()->whereNotNull('hub_id')
            ->orderBy('id')
            ->chunk($chunkSize, function ($mods) use (&$processedCount, &$updatedCount, &$notFoundCount): void {
                // Collect all hub IDs for batch query
                $hubIds = $mods->pluck('hub_id')->filter()->unique()->values();

                if ($hubIds->isEmpty()) {
                    return;
                }

                // Batch fetch hub records by fileID to get fileContentID
                $hubRecords = DB::connection('hub')
                    ->table('filebase1_file_content')
                    ->whereIn('fileID', $hubIds)
                    ->select('fileID', 'fileContentID', 'subject')
                    ->get()
                    ->keyBy('fileID');

                // Process each mod
                foreach ($mods as $mod) {
                    $hubRecord = $hubRecords[$mod->hub_id] ?? null;

                    if (! $hubRecord) {
                        $this->error(sprintf("Mod #%s '%s' - No hub record found for hub_id: %s", $mod->id, $mod->name, $mod->hub_id));
                        $notFoundCount++;
                        $processedCount++;

                        continue;
                    }

                    // Check if update is needed
                    if ($mod->hub_content_id && $mod->hub_content_id == $hubRecord->fileContentID) {
                        $this->line(sprintf("Mod #%s '%s' - Already set to %s", $mod->id, $mod->name, $hubRecord->fileContentID));
                        $processedCount++;

                        continue;
                    }

                    // Update the hub_content_id
                    $oldValue = $mod->hub_content_id ?? 'NULL';
                    $mod->hub_content_id = $hubRecord->fileContentID;
                    $mod->save();

                    $this->info(sprintf("Mod #%s '%s' - Updated from %s to %s", $mod->id, $mod->name, $oldValue, $hubRecord->fileContentID));
                    $updatedCount++;
                    $processedCount++;
                }
            });

        $this->newLine();
        $this->info('Sync Complete!');
        $this->info('Total Processed: '.$processedCount);
        $this->info('Updated: '.$updatedCount);
        $this->info('Not Found in Hub: '.$notFoundCount);

        return Command::SUCCESS;
    }
}
