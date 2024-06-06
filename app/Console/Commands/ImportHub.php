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

class ImportHub extends Command
{
    protected $signature = 'app:import-hub';

    protected $description = 'Connects to the Hub database and imports the data into the Laravel database.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // This may take a minute or two...
        set_time_limit(0);

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

        // Disconnect from the Hub database, clearing temporary tables.
        DB::connection('mysql_hub')->disconnect();

        $this->newLine();
        $this->info('Data imported successfully');
        $this->info('Total execution time: '.round($totalTime[1], 2).'ms');

        $this->newLine();
        $this->info('Refreshing Meilisearch indexes...');
        $this->call('scout:delete-all-indexes');
        $this->call('scout:sync-index-settings');
        $this->call('scout:import', ['model' => '\App\Models\Mod']);

        $this->newLine();
        $this->info('Done');
    }

    protected function loadData(): void
    {
        // We're just going to dump a few things in memory to escape the N+1 problem.
        $this->output->write('Loading data into memory... ');
        $this->bringFileOptionsLocal();
        $this->bringFileContentLocal();
        $this->bringFileVersionLabelsLocal();
        $this->bringFileVersionContentLocal();
        $this->info('Done.');
    }

    protected function importUsers(): void
    {
        $totalInserted = 0;

        foreach (DB::connection('mysql_hub')->table('wcf1_user')->orderBy('userID')->cursor() as $wolt) {
            $registrationDate = Carbon::parse($wolt->registrationDate, 'UTC');
            if ($registrationDate->isFuture()) {
                $registrationDate = now('UTC');
            }
            $registrationDate->setTimezone('UTC');

            $insertData = [
                'hub_id' => $wolt->userID,
                'name' => $wolt->username,
                'email' => mb_convert_case($wolt->email, MB_CASE_LOWER, 'UTF-8'),
                'password' => $this->cleanPasswordHash($wolt->password),
                'created_at' => $registrationDate,
                'updated_at' => now('UTC')->toDateTimeString(),
            ];

            User::upsert($insertData, ['hub_id'], ['name', 'email', 'password', 'created_at', 'updated_at']);
            $totalInserted++;

            // Log every 2500 users processed
            if ($totalInserted % 2500 == 0) {
                $this->line('Processed 2500 users. Total processed so far: '.$totalInserted);
            }
        }

        $this->info('Total users processed: '.$totalInserted);
    }

    protected function cleanPasswordHash(string $password): string
    {
        // The hub passwords are hashed sometimes with a prefix of the hash type. We only want the hash.
        // If it's not Bcrypt, they'll have to reset their password. Tough luck.
        return str_replace(['Bcrypt:', 'cryptMD5:', 'cryptMD5::'], '', $password);
    }

    protected function importLicenses(): void
    {
        $totalInserted = 0;

        DB::connection('mysql_hub')
            ->table('filebase1_license')
            ->chunkById(100, function (Collection $licenses) use (&$totalInserted) {
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

        DB::connection('mysql_hub')
            ->table('wcf1_label')
            ->where('groupID', 1)
            ->chunkById(100, function (Collection $versions) use (&$totalInserted) {
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

        DB::connection('mysql_hub')
            ->table('filebase1_file')
            ->chunkById(100, function (Collection $mods) use (&$command, &$curl, &$totalInserted) {

                foreach ($mods as $mod) {

                    $modContent = DB::table('temp_file_content')
                        ->where('fileID', $mod->fileID)
                        ->orderBy('fileID', 'desc')
                        ->first();

                    $optionSourceCode = DB::table('temp_file_option_values')
                        ->select('optionValue as source_code_link')
                        ->where('fileID', $mod->fileID)
                        ->whereIn('optionID', [5, 1])
                        ->whereNot('optionValue', '')
                        ->orderByDesc('optionID')
                        ->first();

                    $optionContainsAi = DB::table('temp_file_option_values')
                        ->select('optionValue as contains_ai')
                        ->where('fileID', $mod->fileID)
                        ->where('optionID', 7)
                        ->whereNot('optionValue', '')
                        ->first();

                    $optionContainsAds = DB::table('temp_file_option_values')
                        ->select('optionValue as contains_ads')
                        ->where('fileID', $mod->fileID)
                        ->where('optionID', 3)
                        ->whereNot('optionValue', '')
                        ->first();

                    $versionLabel = DB::table('temp_file_version_labels')
                        ->select('labelID')
                        ->where('objectID', $mod->fileID)
                        ->orderBy('labelID', 'desc')
                        ->first();

                    if (empty($versionLabel)) {
                        continue;
                    }

                    $insertData[] = [
                        'hub_id' => (int) $mod->fileID,
                        'user_id' => User::whereHubId($mod->userID)->value('id'),
                        'name' => $modContent?->subject ?? '',
                        'slug' => Str::slug($modContent?->subject) ?? '',
                        'teaser' => Str::limit($modContent?->teaser) ?? '',
                        'description' => $this->convertModDescription($modContent?->message ?? ''),
                        'thumbnail' => $this->fetchModThumbnail($command, $curl, $mod->fileID, $mod->iconHash, $mod->iconExtension),
                        'license_id' => License::whereHubId($mod->licenseID)->value('id'),
                        'source_code_link' => $optionSourceCode?->source_code_link ?? '',
                        'featured' => (bool) $mod->isFeatured,
                        'contains_ai_content' => (bool) $optionContainsAi?->contains_ai ?? false,
                        'contains_ads' => (bool) $optionContainsAds?->contains_ads ?? false,
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

    protected function bringFileOptionsLocal(): void
    {
        DB::statement('CREATE TEMPORARY TABLE temp_file_option_values (
            fileID INT,
            optionID INT,
            optionValue VARCHAR(255)
        )');

        DB::connection('mysql_hub')
            ->table('filebase1_file_option_value')
            ->orderBy('fileID')
            ->chunk(200, function ($options) {
                foreach ($options as $option) {
                    DB::table('temp_file_option_values')->insert([
                        'fileID' => $option->fileID,
                        'optionID' => $option->optionID,
                        'optionValue' => $option->optionValue,
                    ]);
                }
            });
    }

    protected function bringFileContentLocal(): void
    {
        DB::statement('CREATE TEMPORARY TABLE temp_file_content (
            fileID INT,
            subject VARCHAR(255),
            teaser VARCHAR(255),
            message LONGTEXT
        )');

        DB::connection('mysql_hub')
            ->table('filebase1_file_content')
            ->orderBy('fileID')
            ->chunk(200, function ($contents) {
                foreach ($contents as $content) {
                    DB::table('temp_file_content')->insert([
                        'fileID' => $content->fileID,
                        'subject' => $content->subject,
                        'teaser' => $content->teaser,
                        'message' => $content->message,
                    ]);
                }
            });
    }

    protected function fetchModThumbnail($command, $curl, string $fileID, string $thumbnailHash, string $thumbnailExtension): string
    {
        if (empty($fileID) || empty($thumbnailHash) || empty($thumbnailExtension)) {
            return '';
        }

        // Only the first two characters of the icon hash.
        $hashShort = substr($thumbnailHash, 0, 2);

        $hubUrl = "https://hub.sp-tarkov.com/files/images/file/$hashShort/$fileID.$thumbnailExtension";
        $relativePath = "mods/$thumbnailHash.$thumbnailExtension";

        // Check to make sure the image doesn't already exist.
        if (Storage::exists($relativePath)) {
            return $relativePath;
        }

        $command->output->write("Downloading mod thumbnail: $hubUrl... ");
        curl_setopt($curl, CURLOPT_URL, $hubUrl);
        $image = curl_exec($curl);
        if ($image === false) {
            $command->error('Error: '.curl_error($curl));
        } else {
            Storage::put($relativePath, $image);
            $command->info('Done.');
        }

        return $relativePath;
    }

    protected function importModVersions(): void
    {
        $command = $this;
        $totalInserted = 0;

        DB::connection('mysql_hub')
            ->table('filebase1_file_version')
            ->chunkById(500, function (Collection $versions) use (&$command, &$totalInserted) {

                foreach ($versions as $version) {

                    $versionContent = DB::table('temp_file_version_content')
                        ->select('description')
                        ->where('versionID', $version->versionID)
                        ->orderBy('versionID', 'desc')
                        ->first();

                    $optionVirusTotal = DB::table('temp_file_option_values')
                        ->select('optionValue as virus_total_link')
                        ->where('fileID', $version->fileID)
                        ->whereIn('optionID', [6, 2])
                        ->whereNot('optionValue', '')
                        ->orderByDesc('optionID')
                        ->first();

                    $versionLabel = DB::table('temp_file_version_labels')
                        ->select('labelID')
                        ->where('objectID', $version->fileID)
                        ->orderBy('labelID', 'desc')
                        ->first();

                    $modId = Mod::whereHubId($version->fileID)->value('id');

                    if (empty($versionLabel) || empty($modId)) {
                        continue;
                    }

                    $insertData[] = [
                        'hub_id' => $version->versionID,
                        'mod_id' => $modId,
                        'version' => $version->versionNumber,
                        'description' => $this->convertModDescription($versionContent->description ?? ''),
                        'link' => $version->downloadURL,
                        'spt_version_id' => SptVersion::whereHubId($versionLabel->labelID)->value('id'),
                        'virus_total_link' => $optionVirusTotal?->virus_total_link ?? '',
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

    protected function bringFileVersionLabelsLocal(): void
    {
        DB::statement('CREATE TEMPORARY TABLE temp_file_version_labels (
            labelID INT,
            objectID INT
        )');

        DB::connection('mysql_hub')
            ->table('wcf1_label_object')
            ->where('objectTypeID', 387)
            ->orderBy('labelID')
            ->chunk(200, function ($options) {
                foreach ($options as $option) {
                    DB::table('temp_file_version_labels')->insert([
                        'labelID' => $option->labelID,
                        'objectID' => $option->objectID,
                    ]);
                }
            });
    }

    protected function bringFileVersionContentLocal(): void
    {
        DB::statement('CREATE TEMPORARY TABLE temp_file_version_content (
            versionID INT,
            description TEXT
        )');

        DB::connection('mysql_hub')
            ->table('filebase1_file_version_content')
            ->orderBy('versionID')
            ->chunk(200, function ($options) {
                foreach ($options as $option) {
                    DB::table('temp_file_version_content')->insert([
                        'versionID' => $option->versionID,
                        'description' => $option->description,
                    ]);
                }
            });
    }

    protected function convertModDescription(string $description): string
    {
        // Alright, hear me out... Shut up.
        $converter = new HtmlConverter();

        return $converter->convert(Purify::clean($description));
    }
}
