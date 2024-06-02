<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Stevebauman\Purify\Facades\Purify;

class ImportWoltlabData extends Command
{
    protected $signature = 'app:import-woltlab-data';

    protected $description = 'Connects to the Woltlab database and imports the data into the Laravel database.';

    protected array $fileOptionValues = [];

    protected array $fileContent = [];

    protected array $fileVersionContent = [];

    protected array $fileVersionLabels = [];

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->newLine();

        $totalTime = Benchmark::value(function () {
            $loadDataTime = Benchmark::value(function () {
                $this->loadData();
            });
            $this->info('Execution time: '.round($loadDataTime[1], 2).'ms');
            $this->newLine();

            $importUsersTime = Benchmark::value(function () {
                $this->importUsers();
            });
            $this->info('Execution time: '.round($importUsersTime[1], 2).'ms');
            $this->newLine();

            $importLicensesTime = Benchmark::value(function () {
                $this->importLicenses();
            });
            $this->info('Execution time: '.round($importLicensesTime[1], 2).'ms');
            $this->newLine();

            $importSptVersionsTime = Benchmark::value(function () {
                $this->importSptVersions();
            });
            $this->info('Execution time: '.round($importSptVersionsTime[1], 2).'ms');
            $this->newLine();

            $importModsTime = Benchmark::value(function () {
                $this->importMods();
            });
            $this->info('Execution time: '.round($importModsTime[1], 2).'ms');
            $this->newLine();

            $importModVersionsTime = Benchmark::value(function () {
                $this->importModVersions();
            });
            $this->info('Execution time: '.round($importModVersionsTime[1], 2).'ms');
            $this->newLine();
        });

        $this->newLine();
        $this->info('Data imported successfully');
        $this->info('Total execution time: '.round($totalTime[1], 2).'ms');
    }

    protected function loadData(): void
    {
        // We're just going to dump a few things in memory to escape the N+1 problem.
        $this->output->write('Loading data into memory... ');
        $this->fileOptionValues = $this->getFileOptionValues();
        $this->fileContent = $this->getFileContent();
        $this->fileVersionLabels = $this->getFileVersionLabels();
        $this->fileVersionContent = $this->getFileVersionContent();
        $this->info('Done.');
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

            if (! empty($insertData)) {
                User::upsert($insertData, ['hub_id'], ['name', 'email', 'password', 'created_at', 'updated_at']);
                $totalInserted += count($insertData);
                $this->line('Processed '.count($insertData).' users. Total processed so far: '.$totalInserted);
            }

            unset($insertData);
            unset($users);
        }, 'userID');

        $this->info('Total users processed: '.$totalInserted);
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

            if (! empty($insertData)) {
                DB::table('licenses')->upsert($insertData, ['hub_id'], ['name', 'link']);
                $totalInserted += count($insertData);
                $this->line('Processed '.count($insertData).' licenses. Total processed so far: '.$totalInserted);
            }

            unset($insertData);
            unset($licenses);
        }, 'licenseID');

        $this->info('Total licenses processed: '.$totalInserted);
    }

    protected function importSptVersions(): void
    {
        $totalInserted = 0;

        DB::connection('mysql_woltlab')->table('wcf1_label')->where('groupID', 1)->chunkById(100, function (Collection $versions) use (&$totalInserted) {
            $insertData = [];
            foreach ($versions as $version) {
                $insertData[] = [
                    'hub_id' => $version->labelID,
                    'version' => $version->label,
                    'color_class' => $this->translateColour($version->cssClassName),
                ];
            }

            if (! empty($insertData)) {
                DB::table('spt_versions')->upsert($insertData, ['hub_id'], ['version', 'color_class']);
                $totalInserted += count($insertData);
                $this->line('Processed '.count($insertData).' SPT Versions. Total processed so far: '.$totalInserted);
            }

            unset($insertData);
            unset($versions);
        }, 'labelID');

        $this->info('Total licenses processed: '.$totalInserted);
    }

    protected function translateColour(string $colour = ''): string
    {
        return match ($colour) {
            'green' => 'green',
            'slightly-outdated' => 'lime',
            'yellow' => 'yellow',
            'red' => 'red',
            default => 'gray',
        };
    }

    protected function importMods(): void
    {
        $command = $this;
        $totalInserted = 0;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        DB::connection('mysql_woltlab')->table('filebase1_file')->chunkById(100, function (Collection $mods) use (&$command, &$curl, &$totalInserted) {

            foreach ($mods as $mod) {
                $modContent = $this->fileContent[$mod->fileID] ?? [];
                $modOptions = $this->fileOptionValues[$mod->fileID] ?? [];
                $versionLabel = $this->fileVersionLabels[$mod->fileID] ?? [];

                if (empty($versionLabel)) {
                    continue;
                }

                $insertData[] = [
                    'hub_id' => (int) $mod->fileID,
                    'user_id' => User::whereHubId($mod->userID)->value('id'),
                    'name' => $modContent ? $modContent->subject : '',
                    'slug' => $modContent ? Str::slug($modContent->subject) : '',
                    'teaser' => $modContent ? (strlen($modContent->teaser) > 100 ? Str::take($modContent->teaser, 97).'...' : $modContent->teaser) : '',
                    'description' => $this->convertModDescription($modContent?->message ?? ''),
                    'thumbnail' => $this->fetchModThumbnail($command, $curl, $mod->fileID, $mod->iconHash, $mod->iconExtension),
                    'license_id' => License::whereHubId($mod->licenseID)->value('id'),
                    'source_code_link' => $this->fetchSourceLinkValue($modOptions),
                    'featured' => (bool) $mod->isFeatured,
                    'contains_ai_content' => $this->fetchContainsAiContentValue($modOptions),
                    'contains_ads' => $this->fetchContainsAdsValue($modOptions),
                    'disabled' => (bool) $mod->isDisabled,
                    'created_at' => Carbon::parse($mod->time, 'UTC'),
                    'updated_at' => Carbon::parse($mod->lastChangeTime, 'UTC'),
                ];
            }

            if (! empty($insertData)) {
                Mod::upsert($insertData, ['hub_id'], ['user_id', 'name', 'slug', 'teaser', 'description', 'thumbnail', 'license_id', 'source_code_link', 'featured', 'contains_ai_content', 'disabled', 'created_at', 'updated_at']);
                $totalInserted += count($insertData);
                $command->line('Processed '.count($insertData).' mods. Total processed so far: '.$totalInserted);
            }

            unset($insertData);
            unset($mods);
        }, 'fileID');

        curl_close($curl);

        $this->info('Total mods processed: '.$totalInserted);
    }

    protected function getFileOptionValues(): array
    {
        // Fetch all the data from the `filebase1_file_option_value` table.
        $options = DB::connection('mysql_woltlab')->table('filebase1_file_option_value')->get();

        // Convert the collection into an associative array
        $optionValues = [];
        foreach ($options as $option) {
            $optionValues[$option->fileID][] = $option;
        }

        return $optionValues;
    }

    protected function getFileContent(): array
    {
        $content = [];

        // Fetch select data from the `filebase1_file_content` table.
        DB::connection('mysql_woltlab')
            ->table('filebase1_file_content')
            ->select(['fileID', 'subject', 'teaser', 'message'])
            ->orderBy('fileID', 'desc')
            ->chunk(200, function ($contents) use (&$content) {
                foreach ($contents as $contentItem) {
                    $content[$contentItem->fileID] = $contentItem;
                }
            });

        return $content;
    }

    protected function fetchSourceLinkValue(array $options): string
    {
        // Iterate over the options and find the 'optionID' of 5 or 1. Those records will contain the source code link
        // in the 'optionValue' column. The 'optionID' of 5 should take precedence over 1. If neither are found, return
        // an empty string.
        foreach ($options as $option) {
            if ($option->optionID == 5 && ! empty($option->optionValue)) {
                return $option->optionValue;
            }
            if ($option->optionID == 1 && ! empty($option->optionValue)) {
                return $option->optionValue;
            }
        }

        return '';
    }

    protected function fetchContainsAiContentValue(array $options): bool
    {
        // Iterate over the options and find the 'optionID' of 7. That record will contain the AI flag.
        foreach ($options as $option) {
            if ($option->optionID == 7) {
                return (bool) $option->optionValue;
            }
        }

        return false;
    }

    protected function fetchContainsAdsValue(array $options): bool
    {
        // Iterate over the options and find the 'optionID' of 3. That record will contain the Ad flag.
        foreach ($options as $option) {
            if ($option->optionID == 3) {
                return (bool) $option->optionValue;
            }
        }

        return false;
    }

    protected function fetchModThumbnail($command, &$curl, string $fileID, string $thumbnailHash, string $thumbnailExtension): string
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
        curl_setopt($curl, CURLOPT_URL, $hubUrl);
        $image = curl_exec($curl);
        if ($image === false) {
            $command->error('Error: '.curl_error($curl));
        } else {
            Storage::disk('public')->put($localPath, $image);
            $command->info('Done.');
        }

        // Return the path to the saved thumbnail.
        return "/storage/$localPath";
    }

    protected function getFileVersionContent(): array
    {
        $content = [];

        // Fetch select data from the `filebase1_file_version_content` table.
        DB::connection('mysql_woltlab')
            ->table('filebase1_file_version_content')
            ->select(['versionID', 'description'])
            ->orderBy('versionID', 'desc')
            ->chunk(100, function ($contents) use (&$content) {
                foreach ($contents as $contentItem) {
                    $content[$contentItem->versionID] = $content;
                }
            });

        return $content;
    }

    protected function getFileVersionLabels(): array
    {
        $labels = [];

        // Fetch select data from the `wcf1_label_object` table.
        DB::connection('mysql_woltlab')
            ->table('wcf1_label_object')
            ->select(['labelID', 'objectID'])
            ->where('objectTypeID', 387)
            ->orderBy('labelID', 'desc')
            ->chunk(100, function ($labelData) use (&$labels) {
                foreach ($labelData as $labelItem) {
                    $labels[$labelItem->objectID] = $labelItem->labelID;
                }
            });

        return $labels;
    }

    protected function importModVersions(): void
    {
        $command = $this;
        $totalInserted = 0;

        DB::connection('mysql_woltlab')->table('filebase1_file_version')->chunkById(500, function (Collection $versions) use (&$command, &$totalInserted) {

            foreach ($versions as $version) {
                $versionContent = $this->fileVersionContent[$version->versionID] ?? [];
                $modOptions = $this->fileOptionValues[$version->fileID] ?? [];
                $versionLabel = $this->fileVersionLabels[$version->fileID] ?? [];

                $modId = Mod::whereHubId($version->fileID)->value('id');

                if (empty($versionLabel) || empty($modId)) {
                    continue;
                }

                $insertData[] = [
                    'hub_id' => $version->versionID,
                    'mod_id' => $modId,
                    'version' => $version->versionNumber,
                    'description' => $this->convertModDescription($versionContent['description'] ?? ''),
                    'link' => $version->downloadURL,
                    'spt_version_id' => SptVersion::whereHubId($versionLabel)->value('id'),
                    'virus_total_link' => $this->fetchVirusTotalLink($modOptions),
                    'downloads' => max((int) $version->downloads, 0), // Ensure the value is at least 0
                    'disabled' => (bool) $version->isDisabled,
                    'created_at' => Carbon::parse($version->uploadTime, 'UTC'),
                    'updated_at' => Carbon::parse($version->uploadTime, 'UTC'),
                ];
            }

            if (! empty($insertData)) {
                ModVersion::upsert($insertData, ['hub_id'], ['mod_id', 'version', 'description', 'link', 'spt_version_id', 'virus_total_link', 'downloads', 'created_at', 'updated_at']);
                $totalInserted += count($insertData);
                $command->line('Processed '.count($insertData).' mod versions. Total processed so far: '.$totalInserted);
            }

            unset($insertData);
            unset($version);
        }, 'versionID');

        $this->info('Total mod versions processed: '.$totalInserted);
    }

    protected function fetchVirusTotalLink(array $options): string
    {
        // Iterate over the options and find the 'optionID' of 6 or 2. Those records will contain the Virus Total link
        // in the 'optionValue' column. The 'optionID' of 6 should take precedence over 1. If neither are found, return
        // an empty string.
        foreach ($options as $option) {
            if ($option->optionID == 6 && ! empty($option->optionValue)) {
                return $option->optionValue;
            }
            if ($option->optionID == 2 && ! empty($option->optionValue)) {
                return $option->optionValue;
            }
        }

        return '';
    }

    protected function convertModDescription(string $description): string
    {
        // Alright, hear me out... Shut up.
        $converter = new HtmlConverter();

        return $converter->convert(Purify::clean($description));
    }
}
