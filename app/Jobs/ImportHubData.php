<?php

namespace App\Jobs;

use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Carbon\Carbon;
use CurlHandle;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Stevebauman\Purify\Facades\Purify;

class ImportHubData implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Stream some data locally so that we don't have to keep accessing the Hub's database. Use MySQL temporary
        // tables to store the data to save on memory; we don't want this to be a memory hog.
        $this->bringFileOptionsLocal();
        $this->bringFileContentLocal();
        $this->bringFileVersionLabelsLocal();
        $this->bringFileVersionContentLocal();

        // Begin to import the data into the permanent local database tables.
        $this->importUsers();
        $this->importLicenses();
        $this->importSptVersions();
        $this->importMods();
        $this->importModVersions();

        // Ensure that we've disconnected from the Hub database, clearing temporary tables.
        DB::connection('mysql_hub')->disconnect();

        // Reindex the Meilisearch index.
        Artisan::call('scout:delete-all-indexes');
        Artisan::call('scout:sync-index-settings');
        Artisan::call('scout:import', ['model' => '\App\Models\Mod']);
    }

    /**
     * Bring the file options from the Hub database to the local database temporary table.
     */
    protected function bringFileOptionsLocal(): void
    {
        if (Schema::hasTable('temp_file_option_values')) {
            DB::statement('DROP TABLE temp_file_option_values');
        }
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
                        'fileID' => (int) $option->fileID,
                        'optionID' => (int) $option->optionID,
                        'optionValue' => $option->optionValue,
                    ]);
                }
            });
    }

    /**
     * Bring the file content from the Hub database to the local database temporary table.
     */
    protected function bringFileContentLocal(): void
    {
        if (Schema::hasTable('temp_file_content')) {
            DB::statement('DROP TABLE temp_file_content');
        }
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
                        'fileID' => (int) $content->fileID,
                        'subject' => $content->subject,
                        'teaser' => $content->teaser,
                        'message' => $content->message,
                    ]);
                }
            });
    }

    /**
     * Bring the file version labels from the Hub database to the local database temporary table.
     */
    protected function bringFileVersionLabelsLocal(): void
    {
        if (Schema::hasTable('temp_file_version_labels')) {
            DB::statement('DROP TABLE temp_file_version_labels');
        }
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
                        'labelID' => (int) $option->labelID,
                        'objectID' => (int) $option->objectID,
                    ]);
                }
            });
    }

    /**
     * Bring the file version content from the Hub database to the local database temporary table.
     */
    protected function bringFileVersionContentLocal(): void
    {
        if (Schema::hasTable('temp_file_version_content')) {
            DB::statement('DROP TABLE temp_file_version_content');
        }
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
                        'versionID' => (int) $option->versionID,
                        'description' => $option->description,
                    ]);
                }
            });
    }

    /**
     * Import the users from the Hub database to the local database.
     */
    protected function importUsers(): void
    {
        DB::connection('mysql_hub')
            ->table('wcf1_user')
            ->select('userID', 'username', 'email', 'password', 'registrationDate')
            ->chunkById(250, function (Collection $users) {

                $insertData = [];
                foreach ($users as $user) {
                    $insertData[] = [
                        'hub_id' => (int) $user->userID,
                        'name' => $user->username,
                        'email' => mb_convert_case($user->email, MB_CASE_LOWER, 'UTF-8'),
                        'password' => $this->cleanPasswordHash($user->password),
                        'created_at' => $this->cleanRegistrationDate($user->registrationDate),
                        'updated_at' => now('UTC')->toDateTimeString(),
                    ];
                }

                if (! empty($insertData)) {
                    DB::table('users')->upsert(
                        $insertData,
                        ['hub_id'],
                        ['name', 'email', 'password', 'created_at', 'updated_at']
                    );
                }
            }, 'userID');
    }

    /**
     * Clean the password hash from the Hub database.
     */
    protected function cleanPasswordHash(string $password): string
    {
        // The hub passwords are hashed sometimes with a prefix of the hash type. We only want the hash.
        // If it's not Bcrypt, they'll have to reset their password. Tough luck.
        $clean = str_ireplace(['invalid:', 'bcrypt:', 'bcrypt::', 'cryptmd5:', 'cryptmd5::'], '', $password);

        // If the password hash starts with $2, it's a valid Bcrypt hash. Otherwise, it's invalid.
        return str_starts_with($clean, '$2') ? $clean : '';
    }

    /**
     * Clean the registration date from the Hub database.
     */
    protected function cleanRegistrationDate(string $registrationDate): string
    {
        $date = Carbon::createFromTimestamp($registrationDate);

        // If the registration date is in the future, set it to now.
        if ($date->isFuture()) {
            $date = Carbon::now('UTC');
        }

        return $date->toDateTimeString();
    }

    /**
     * Import the licenses from the Hub database to the local database.
     */
    protected function importLicenses(): void
    {
        DB::connection('mysql_hub')
            ->table('filebase1_license')
            ->chunkById(100, function (Collection $licenses) {

                $insertData = [];
                foreach ($licenses as $license) {
                    $insertData[] = [
                        'hub_id' => (int) $license->licenseID,
                        'name' => $license->licenseName,
                        'link' => $license->licenseURL,
                    ];
                }

                if (! empty($insertData)) {
                    DB::table('licenses')->upsert($insertData, ['hub_id'], ['name', 'link']);
                }
            }, 'licenseID');
    }

    /**
     * Import the SPT versions from the Hub database to the local database.
     */
    protected function importSptVersions(): void
    {
        DB::connection('mysql_hub')
            ->table('wcf1_label')
            ->where('groupID', 1)
            ->chunkById(100, function (Collection $versions) {
                $insertData = [];
                foreach ($versions as $version) {
                    $insertData[] = [
                        'hub_id' => (int) $version->labelID,
                        'version' => $version->label,
                        'color_class' => $this->translateColour($version->cssClassName),
                    ];
                }

                if (! empty($insertData)) {
                    DB::table('spt_versions')->upsert($insertData, ['hub_id'], ['version', 'color_class']);
                }
            }, 'labelID');
    }

    /**
     * Translate the colour class from the Hub database to the local database.
     */
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

    /**
     * Import the mods from the Hub database to the local database.
     */
    protected function importMods(): void
    {
        // Initialize a cURL handler for downloading mod thumbnails.
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        DB::connection('mysql_hub')
            ->table('filebase1_file')
            ->chunkById(100, function (Collection $mods) use ($curl) {

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

                    // Skip the mod if it doesn't have a version label attached to it.
                    if (empty($versionLabel)) {
                        continue;
                    }

                    $insertData[] = [
                        'hub_id' => (int) $mod->fileID,
                        'user_id' => User::whereHubId($mod->userID)->value('id'),
                        'name' => $modContent?->subject ?? '',
                        'slug' => Str::slug($modContent?->subject ?? ''),
                        'teaser' => Str::limit($modContent?->teaser ?? ''),
                        'description' => $this->cleanHubContent($modContent?->message ?? ''),
                        'thumbnail' => $this->fetchModThumbnail($curl, $mod->fileID, $mod->iconHash, $mod->iconExtension),
                        'license_id' => License::whereHubId($mod->licenseID)->value('id'),
                        'source_code_link' => $optionSourceCode?->source_code_link ?? '',
                        'featured' => (bool) $mod?->isFeatured,
                        'contains_ai_content' => (bool) $optionContainsAi?->contains_ai,
                        'contains_ads' => (bool) $optionContainsAds?->contains_ads,
                        'disabled' => (bool) $mod?->isDisabled,
                        'created_at' => Carbon::parse($mod->time, 'UTC'),
                        'updated_at' => Carbon::parse($mod->lastChangeTime, 'UTC'),
                    ];
                }

                if (! empty($insertData)) {
                    Mod::upsert($insertData, ['hub_id'], [
                        'user_id',
                        'name',
                        'slug',
                        'teaser',
                        'description',
                        'thumbnail',
                        'license_id',
                        'source_code_link',
                        'featured',
                        'contains_ai_content',
                        'disabled',
                        'created_at',
                        'updated_at',
                    ]);
                }
            }, 'fileID');

        // Close the cURL handler.
        curl_close($curl);
    }

    /**
     * Convert the mod description from WoltHub flavoured HTML to Markdown.
     */
    protected function cleanHubContent(string $dirty): string
    {
        // Alright, hear me out... Shut up.

        $converter = new HtmlConverter();
        $clean = Purify::clean($dirty);

        return $converter->convert($clean);
    }

    /**
     * Fetch the mod thumbnail from the Hub and store it anew.
     */
    protected function fetchModThumbnail(CurlHandle $curl, string $fileID, string $thumbnailHash, string $thumbnailExtension): string
    {
        // If any of the required fields are empty, return an empty string.
        if (empty($fileID) || empty($thumbnailHash) || empty($thumbnailExtension)) {
            return '';
        }

        // Build some paths/URLs using the mod data.
        $hashShort = substr($thumbnailHash, 0, 2);
        $fileName = $fileID.'.'.$thumbnailExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/files/images/file/'.$hashShort.'/'.$fileName;
        $relativePath = 'mods/'.$fileName;

        // Determine the disk to use based on the environment.
        $disk = match (config('app.env')) {
            'production' => 'r2', // Cloudflare R2 Storage
            default => 'public', // Local
        };

        // Check to make sure the image doesn't already exist.
        if (Storage::disk($disk)->exists($relativePath)) {
            return $relativePath; // Already exists, return the path.
        }

        // Download the image using the cURL handler.
        curl_setopt($curl, CURLOPT_URL, $hubUrl);
        $image = curl_exec($curl);

        if ($image === false) {
            Log::error('There was an error attempting to download a mod thumbnail. cURL error: '.curl_error($curl));

            return '';
        }

        // Store the image on the disk.
        Storage::disk($disk)->put($relativePath, $image);

        return $relativePath;
    }

    /**
     * Import the mod versions from the Hub database to the local database.
     */
    protected function importModVersions(): void
    {
        DB::connection('mysql_hub')
            ->table('filebase1_file_version')
            ->chunkById(500, function (Collection $versions) {

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

                    // Skip the mod version if it doesn't have a mod or version label attached to it.
                    if (empty($versionLabel) || empty($modId)) {
                        continue;
                    }

                    $insertData[] = [
                        'hub_id' => (int) $version->versionID,
                        'mod_id' => $modId,
                        'version' => $version->versionNumber,
                        'description' => $this->cleanHubContent($versionContent->description ?? ''),
                        'link' => $version->downloadURL,
                        'spt_version_id' => SptVersion::whereHubId($versionLabel->labelID)->value('id'),
                        'virus_total_link' => $optionVirusTotal?->virus_total_link ?? '',
                        'downloads' => max((int) $version->downloads, 0), // At least 0.
                        'disabled' => (bool) $version->isDisabled,
                        'created_at' => Carbon::parse($version->uploadTime, 'UTC'),
                        'updated_at' => Carbon::parse($version->uploadTime, 'UTC'),
                    ];
                }

                if (! empty($insertData)) {
                    ModVersion::upsert($insertData, ['hub_id'], [
                        'mod_id',
                        'version',
                        'description',
                        'link',
                        'spt_version_id',
                        'virus_total_link',
                        'downloads',
                        'created_at',
                        'updated_at',
                    ]);
                }
            }, 'versionID');
    }

    /**
     * The job failed to process.
     */
    public function failed(Exception $exception): void
    {
        // Disconnect from the 'mysql_hub' database connection
        DB::connection('mysql_hub')->disconnect();
    }
}
