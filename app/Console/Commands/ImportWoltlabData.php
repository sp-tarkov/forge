<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportWoltlabData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-woltlab-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connects to the Woltlab database and imports the data into the Laravel database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->importUsers();
        $this->importLicenses();
        $this->importMods();
        $this->info('Data imported successfully.');
    }

    protected function importUsers(): void
    {
        $totalInserted = 0;

        DB::connection('mysql_woltlab')->table('wcf1_user')->chunkById(2500, function (Collection $users) use (&$totalInserted) {
            $insertData = [];
            foreach ($users as $wolt) {
                $registrationDate = Carbon::parse($wolt->registrationDate, 'UTC');
                if ($registrationDate->isFuture()) {
                    $registrationDate = now('UTC');
                }
                $registrationDate->setTimezone('UTC');

                $insertData[] = [
                    'hub_id' => $wolt->userID,
                    'name' => $wolt->username,
                    'email' => mb_convert_case($wolt->email, MB_CASE_LOWER, 'UTF-8'),
                    'password' => $wolt->password,
                    'created_at' => $registrationDate,
                    'updated_at' => now('UTC')->toDateTimeString(),
                ];
            }

            if (!empty($insertData)) {
                User::upsert($insertData, ['hub_id'], ['name', 'email', 'password', 'created_at', 'updated_at']);
                $totalInserted += count($insertData);
                $this->line('Processed ' . count($insertData) . ' users. Total processed so far: ' . $totalInserted);
            }

            unset($insertData);
            unset($users);
        }, 'userID');

        $this->info('Total users processed: ' . $totalInserted);
        $this->newLine();
    }

    protected function importLicenses(): void
    {
        $totalInserted = 0;

        DB::connection('mysql_woltlab')->table('filebase1_license')->chunkById(100, function (Collection $licenses) use (&$totalInserted) {
            $insertData = [];
            foreach ($licenses as $license) {
                $insertData[] = [
                    'hub_id' => $license->licenseID,
                    'name' => $license->licenseName,
                    'link' => $license->licenseURL,
                ];
            }

            if (!empty($insertData)) {
                DB::table('licenses')->upsert($insertData, ['hub_id'], ['name', 'link']);
                $totalInserted += count($insertData);
                $this->line('Processed ' . count($insertData) . ' licenses. Total processed so far: ' . $totalInserted);
            }

            unset($insertData);
            unset($licenses);
        }, 'licenseID');

        $this->info('Total licenses processed: ' . $totalInserted);
        $this->newLine();
    }

    protected function importMods(): void
    {
        $command = $this;
        $totalInserted = 0;

        DB::connection('mysql_woltlab')->table('filebase1_file')->chunkById(5, function (Collection $mods) use (&$command, &$totalInserted) {

            foreach ($mods as $mod)
            {
                $modContent = DB::connection('mysql_woltlab')->table('filebase1_file_content')->where('fileID', $mod->fileID)->first();

                $insertData[] = [
                    'hub_id' => $mod->fileID,
                    'user_id' => User::whereName($mod->username)->value('id'),
                    'name' => $modContent->subject,
                    'slug' => Str::slug($modContent->subject),
                    'teaser' => $modContent->teaser,
                    'description' => $modContent->message,
                    'thumbnail' => $this->fetchModThumbnail($command, $mod->fileID, $mod->iconHash, $mod->iconExtension),
                    'license_id' => License::whereHubId($mod->licenseID)->value('id'),
                    'source_code_link' => $this->fetchSourceLinkValue($mod->fileID),
                    'featured' => $mod->isFeatured,
                    'contains_ai_content' => $this->fetchContainsAiContentValue($mod->fileID),
                    'disabled' => $mod->isDisabled,
                    'created_at' => Carbon::parse($mod->time, 'UTC'),
                    'updated_at' => Carbon::parse($mod->lastChangeTime, 'UTC'),
                ];
            }

            if (!empty($insertData)) {
                Mod::upsert($insertData, ['hub_id'], ['user_id', 'name', 'slug', 'teaser', 'description', 'thumbnail', 'license_id', 'source_code_link', 'featured', 'contains_ai_content', 'disabled', 'created_at', 'updated_at']);
                $totalInserted += count($insertData);
                $command->line('Processed ' . count($insertData) . ' mods. Total processed so far: ' . $totalInserted);
            }

            unset($insertData);
            unset($mods);
        }, 'fileID');

        $this->info('Total mods processed: ' . $totalInserted);
        $this->newLine();
    }

    protected function fetchSourceLinkValue(string $fileID): string
    {
        $options = DB::connection('mysql_woltlab')->table('filebase1_file_option_value')->where('fileID', $fileID)->get();

        // Iterate over the options and find the 'optionID' of 5 or 1. That record will contain the source code link in
        // the 'optionValue' column. The 'optionID' of 5 should take precedence over 1. If neither are found, return an
        // empty string.
        foreach ($options as $option) {
            if ($option->optionID == 5 && !empty($option->optionValue)) {
                return $option->optionValue;
            }
            if ($option->optionID == 1 && !empty($option->optionValue)) {
                return $option->optionValue;
            }
        }
        return '';
    }

    protected function fetchContainsAiContentValue(string $fileID): bool
    {
        $options = DB::connection('mysql_woltlab')->table('filebase1_file_option_value')->where('fileID', $fileID)->get();

        // Iterate over the options and find the 'optionID' of 7. That record will contain the AI flag.
        foreach ($options as $option) {
            if ($option->optionID == 7) {
                return (bool) $option->optionValue;
            }
        }
        return '';
    }

    protected function fetchModThumbnail($command, string $fileID, string $thumbnailHash, string $thumbnailExtension): string
    {
        if (empty($fileID) || empty($thumbnailHash) || empty($thumbnailExtension)) {
            return '';
        }

        // Only the first two characters of the icon hash.
        $hashShort = substr($thumbnailHash, 0, 2);

        $hubUrl = "https://hub.sp-tarkov.com/files/images/file/$hashShort/$fileID.$thumbnailExtension";
        $localPath = "mods/$thumbnailHash.$thumbnailExtension";

        // Check to make sure the image doesn't already exist.
        if (Storage::disk('public')->exists($localPath)) {
            return "/storage/$localPath";
        }

        $command->output->write("Downloading mod thumbnail: $hubUrl... ");
        Storage::disk('public')->put($localPath, file_get_contents($hubUrl));
        $command->info('Done.');

        // Return the path to the saved thumbnail.
        return "/storage/$localPath";
    }
}
