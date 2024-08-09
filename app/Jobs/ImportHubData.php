<?php

namespace App\Jobs;

use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use CurlHandle;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;
use Stevebauman\Purify\Facades\Purify;

class ImportHubData implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Stream some data locally so that we don't have to keep accessing the Hub's database. Use MySQL temporary
        // tables to store the data to save on memory; we don't want this to be a hog.
        $this->bringUserAvatarLocal();
        $this->bringFileAuthorsLocal();
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

        // Re-sync search.
        Artisan::call('app:search-sync');

        Artisan::call('cache:clear');
    }

    /**
     * Bring the user avatar table from the Hub database to the local database temporary table.
     */
    protected function bringUserAvatarLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_user_avatar');
        DB::statement('CREATE TEMPORARY TABLE temp_user_avatar (
            avatarID INT,
            avatarExtension VARCHAR(255),
            userID INT,
            fileHash VARCHAR(255)
        )');

        DB::connection('mysql_hub')
            ->table('wcf1_user_avatar')
            ->orderBy('avatarID')
            ->chunk(200, function ($avatars) {
                foreach ($avatars as $avatar) {
                    DB::table('temp_user_avatar')->insert([
                        'avatarID' => (int) $avatar->avatarID,
                        'avatarExtension' => $avatar->avatarExtension,
                        'userID' => (int) $avatar->userID,
                        'fileHash' => $avatar->fileHash,
                    ]);
                }
            });
    }

    /**
     * Bring the file authors from the Hub database to the local database temporary table.
     */
    protected function bringFileAuthorsLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_author');
        DB::statement('CREATE TEMPORARY TABLE temp_file_author (fileID INT, userID INT) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('filebase1_file_author')
            ->orderBy('fileID')
            ->chunk(200, function ($relationships) {
                foreach ($relationships as $relationship) {
                    DB::table('temp_file_author')->insert([
                        'fileID' => (int) $relationship->fileID,
                        'userID' => (int) $relationship->userID,
                    ]);
                }
            });
    }

    /**
     * Bring the file options from the Hub database to the local database temporary table.
     */
    protected function bringFileOptionsLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_option_values');
        DB::statement('CREATE TEMPORARY TABLE temp_file_option_values (fileID INT, optionID INT, optionValue VARCHAR(255)) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

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
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_content');
        DB::statement('CREATE TEMPORARY TABLE temp_file_content (fileID INT, subject VARCHAR(255), teaser VARCHAR(255), message LONGTEXT) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

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
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_version_labels');
        DB::statement('CREATE TEMPORARY TABLE temp_file_version_labels (labelID INT, objectID INT) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

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
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_version_content');
        DB::statement('CREATE TEMPORARY TABLE temp_file_version_content (versionID INT, description TEXT) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

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
        // Initialize a cURL handler for downloading mod thumbnails.
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        DB::connection('mysql_hub')
            ->table('wcf1_user as u')
            ->select(
                'u.userID',
                'u.username',
                'u.email',
                'u.password',
                'u.registrationDate',
                'u.banned',
                'u.banReason',
                'u.banExpires',
                'u.coverPhotoHash',
                'u.coverPhotoExtension',
                'u.rankID',
                'r.rankTitle',
            )
            ->leftJoin('wcf1_user_rank as r', 'u.rankID', '=', 'r.rankID')
            ->chunkById(250, function (Collection $users) use ($curl) {
                $userData = $bannedUsers = $userRanks = [];

                foreach ($users as $user) {
                    $userData[] = $this->collectUserData($curl, $user);

                    $bannedUserData = $this->collectBannedUserData($user);
                    if ($bannedUserData) {
                        $bannedUsers[] = $bannedUserData;
                    }

                    $userRankData = $this->collectUserRankData($user);
                    if ($userRankData) {
                        $userRanks[] = $userRankData;
                    }
                }

                $this->upsertUsers($userData);
                $this->handleBannedUsers($bannedUsers);
                $this->handleUserRoles($userRanks);
            }, 'userID');

        // Close the cURL handler.
        curl_close($curl);
    }

    protected function collectUserData(CurlHandle $curl, object $user): array
    {
        return [
            'hub_id' => (int) $user->userID,
            'name' => $user->username,
            'email' => Str::lower($user->email),
            'password' => $this->cleanPasswordHash($user->password),
            'profile_photo_path' => $this->fetchUserAvatar($curl, $user),
            'cover_photo_path' => $this->fetchUserCoverPhoto($curl, $user),
            'created_at' => $this->cleanRegistrationDate($user->registrationDate),
            'updated_at' => now('UTC')->toDateTimeString(),
        ];
    }

    /**
     * Clean the password hash from the Hub database.
     */
    protected function cleanPasswordHash(string $password): string
    {
        // The hub passwords sometimes hashed with a prefix of the hash type. We only want the hash.
        // If it's not Bcrypt, they'll have to reset their password. Tough luck.
        $clean = str_ireplace(['invalid:', 'bcrypt:', 'bcrypt::', 'cryptmd5:', 'cryptmd5::'], '', $password);

        // At this point, if the password hash starts with $2, it's a valid Bcrypt hash. Otherwise, it's invalid.
        return str_starts_with($clean, '$2') ? $clean : '';
    }

    /**
     * Fetch the user avatar from the Hub and store it anew.
     */
    protected function fetchUserAvatar(CurlHandle $curl, object $user): string
    {
        // Fetch the user's avatar data from the temporary table.
        $avatar = DB::table('temp_user_avatar')->where('userID', $user->userID)->first();

        if (! $avatar) {
            return '';
        }

        $hashShort = substr($avatar->fileHash, 0, 2);
        $fileName = $avatar->fileHash.'.'.$avatar->avatarExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/images/avatars/'.$hashShort.'/'.$avatar->avatarID.'-'.$fileName;
        $relativePath = 'user-avatars/'.$fileName;

        return $this->fetchAndStoreImage($curl, $hubUrl, $relativePath);
    }

    /**
     * Fetch and store an image from the Hub.
     */
    protected function fetchAndStoreImage(CurlHandle $curl, string $hubUrl, string $relativePath): string
    {
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
            Log::error('There was an error attempting to download the image. cURL error: '.curl_error($curl));

            return '';
        }

        // Store the image on the disk.
        Storage::disk($disk)->put($relativePath, $image);

        return $relativePath;
    }

    /**
     * Fetch the user avatar from the Hub and store it anew.
     */
    protected function fetchUserCoverPhoto(CurlHandle $curl, object $user): string
    {
        if (empty($user->coverPhotoHash) || empty($user->coverPhotoExtension)) {
            return '';
        }

        $hashShort = substr($user->coverPhotoHash, 0, 2);
        $fileName = $user->coverPhotoHash.'.'.$user->coverPhotoExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/images/coverPhotos/'.$hashShort.'/'.$user->userID.'-'.$fileName;
        $relativePath = 'user-covers/'.$fileName;

        return $this->fetchAndStoreImage($curl, $hubUrl, $relativePath);
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
     * Build an array of banned user data ready to be inserted into the local database.
     */
    protected function collectBannedUserData($user): ?array
    {
        if ($user->banned) {
            return [
                'hub_id' => (int) $user->userID,
                'comment' => $user->banReason ?? '',
                'expired_at' => $this->cleanUnbannedAtDate($user->banExpires),
            ];
        }

        return null;
    }

    /**
     * Clean the banned_at date from the Hub database.
     */
    protected function cleanUnbannedAtDate(?string $unbannedAt): ?string
    {
        // If the input is null, return null
        if ($unbannedAt === null) {
            return null;
        }

        // Explicit check for the Unix epoch start date
        if (Str::contains($unbannedAt, '1970-01-01')) {
            return null;
        }

        // Use validator to check for a valid date format
        $validator = Validator::make(['date' => $unbannedAt], [
            'date' => 'date_format:Y-m-d H:i:s',
        ]);
        if ($validator->fails()) {
            // If the date format is invalid, return null
            return null;
        }

        // Validate the date using Carbon
        try {
            $date = Carbon::parse($unbannedAt);

            // Additional check to ensure the date is not a default or zero date
            if ($date->year == 0 || $date->month == 0 || $date->day == 0) {
                return null;
            }

            return $date->toDateTimeString();
        } catch (\Exception $e) {
            // If the date is not valid, return null
            return null;
        }
    }

    protected function collectUserRankData($user): ?array
    {
        if ($user->rankID && $user->rankTitle) {
            return [
                'hub_id' => (int) $user->userID,
                'title' => $user->rankTitle,
            ];
        }

        return null;
    }

    /**
     * Insert or update the users in the local database.
     */
    protected function upsertUsers($usersData): void
    {
        if (! empty($usersData)) {
            DB::table('users')->upsert($usersData, ['hub_id'], [
                'name',
                'email',
                'password',
                'created_at',
                'updated_at',
            ]);
        }
    }

    /**
     * Fetch the hub-banned users from the local database and ban them locally.
     */
    protected function handleBannedUsers($bannedUsers): void
    {
        foreach ($bannedUsers as $bannedUser) {
            $user = User::whereHubId($bannedUser['hub_id'])->first();
            $user->ban([
                'comment' => $bannedUser['comment'],
                'expired_at' => $bannedUser['expired_at'],
            ]);
        }
    }

    /**
     * Fetch or create the user ranks in the local database and assign them to the users.
     */
    protected function handleUserRoles($userRanks): void
    {
        foreach ($userRanks as $userRank) {
            $roleName = Str::ucfirst(Str::afterLast($userRank['title'], '.'));
            $roleData = $this->buildUserRoleData($roleName);
            UserRole::upsert($roleData, ['name'], ['name', 'short_name', 'description', 'color_class']);

            $userRole = UserRole::whereName($roleData['name'])->first();
            $user = User::whereHubId($userRank['hub_id'])->first();
            $user->assignRole($userRole);
        }
    }

    /**
     * Build the user role data based on the role name.
     */
    protected function buildUserRoleData(string $name): array
    {
        if ($name === 'Administrator') {
            return [
                'name' => 'Administrator',
                'short_name' => 'Admin',
                'description' => 'An administrator has full access to the site.',
                'color_class' => 'sky',
            ];
        }

        if ($name === 'Moderator') {
            return [
                'name' => 'Moderator',
                'short_name' => 'Mod',
                'description' => 'A moderator has the ability to moderate user content.',
                'color_class' => 'emerald',
            ];
        }

        return [
            'name' => $name,
            'short_name' => '',
            'description' => '',
            'color_class' => '',
        ];
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
                    // Fetch any additional authors for the mod.
                    $modAuthors = DB::table('temp_file_author')
                        ->where('fileID', $mod->fileID)
                        ->pluck('userID')
                        ->toArray();
                    $modAuthors[] = $mod->userID; // Add the primary author to the list.
                    $modAuthors = User::whereIn('hub_id', $modAuthors)->pluck('id')->toArray(); // Replace with local IDs.

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

                    $modData[] = [
                        'hub_id' => (int) $mod->fileID,
                        'users' => $modAuthors,
                        'name' => $modContent?->subject ?? '',
                        'slug' => Str::slug($modContent?->subject ?? ''),
                        'teaser' => Str::limit($modContent?->teaser ?? ''),
                        'description' => $this->cleanHubContent($modContent?->message ?? ''),
                        'thumbnail' => $this->fetchModThumbnail($curl, $mod->fileID, $mod->iconHash, $mod->iconExtension),
                        'license_id' => License::whereHubId($mod->licenseID)->value('id'),
                        'source_code_link' => $optionSourceCode?->source_code_link ?? '',
                        'featured' => (bool) $mod->isFeatured,
                        'contains_ai_content' => (bool) $optionContainsAi?->contains_ai,
                        'contains_ads' => (bool) $optionContainsAds?->contains_ads,
                        'disabled' => (bool) $mod->isDisabled,
                        'published_at' => Carbon::parse($mod->time, 'UTC'),
                        'created_at' => Carbon::parse($mod->time, 'UTC'),
                        'updated_at' => Carbon::parse($mod->lastChangeTime, 'UTC'),
                    ];
                }

                if (! empty($modData)) {
                    // Remove the user_id from the mod data before upserting.
                    $insertModData = array_map(fn ($mod) => Arr::except($mod, 'users'), $modData);

                    Mod::withoutGlobalScopes()->upsert($insertModData, ['hub_id'], [
                        'name',
                        'slug',
                        'teaser',
                        'description',
                        'thumbnail',
                        'license_id',
                        'source_code_link',
                        'featured',
                        'contains_ai_content',
                        'contains_ads',
                        'disabled',
                        'published_at',
                        'created_at',
                        'updated_at',
                    ]);

                    foreach ($modData as $mod) {
                        Mod::whereHubId($mod['hub_id'])->first()?->users()->sync($mod['users']);
                    }
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

        $converter = new HtmlConverter;
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

        return $this->fetchAndStoreImage($curl, $hubUrl, $relativePath);
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
                        'published_at' => Carbon::parse($version->uploadTime, 'UTC'),
                        'created_at' => Carbon::parse($version->uploadTime, 'UTC'),
                        'updated_at' => Carbon::parse($version->uploadTime, 'UTC'),
                    ];
                }

                if (! empty($insertData)) {
                    ModVersion::withoutGlobalScopes()->upsert($insertData, ['hub_id'], [
                        'mod_id',
                        'version',
                        'description',
                        'link',
                        'spt_version_id',
                        'virus_total_link',
                        'downloads',
                        'published_at',
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
        // Explicitly drop the temporary tables.
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_user_avatar');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_author');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_option_values');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_content');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_version_labels');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_version_content');

        // Close the connections. This should drop the temporary tables as well, but I like to be explicit.
        DB::connection('mysql_hub')->disconnect();
        DB::disconnect();
    }
}
