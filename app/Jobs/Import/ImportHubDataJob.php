<?php

declare(strict_types=1);

namespace App\Jobs\Import;

use App\Exceptions\InvalidVersionNumberException;
use App\Jobs\Import\DataTransferObjects\HubUser;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Version;
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
use Throwable;

class ImportHubDataJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        // Stream some data locally so that we don't have to keep accessing the Hub's database. Use MySQL temporary
        // tables to store the data to save on memory; we don't want this to be a hog.
        $this->bringUserAvatarLocal();
        $this->bringUserOptionsLocal();
        $this->bringFileAuthorsLocal();
        $this->bringFileOptionsLocal();
        $this->bringFileContentLocal();
        $this->bringFileVersionLabelsLocal();
        $this->bringFileVersionContentLocal();
        $this->bringSptVersionTagsLocal();

        // Begin to import the data into the permanent local database tables.
        $this->importUsers();
        $this->importUserFollows();
        $this->importLicenses();
        $this->importSptVersions();
        $this->importMods();
        $this->importModVersions();

        // Remove mods that are no longer on the hub.
        $this->removeDeletedMods();

        // Ensure that we've disconnected from the Hub database, clearing temporary tables.
        DB::connection('mysql_hub')->disconnect();

        Artisan::call('app:search-sync');
        Artisan::call('app:resolve-versions');
        Artisan::call('app:count-mods');
        Artisan::call('app:update-downloads');
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
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('wcf1_user_avatar')
            ->orderBy('avatarID')
            ->chunk(200, function ($avatars): void {
                $insertData = [];
                foreach ($avatars as $avatar) {
                    $insertData[] = [
                        'avatarID' => (int) $avatar->avatarID,
                        'avatarExtension' => $avatar->avatarExtension,
                        'userID' => (int) $avatar->userID,
                        'fileHash' => $avatar->fileHash,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_user_avatar')->insert($insertData);
                }
            });
    }

    /**
     * Bring the user options table from the Hub database to the local database temporary table.
     */
    private function bringUserOptionsLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_user_options_values');
        DB::statement('CREATE TEMPORARY TABLE temp_user_options_values (
            userID INT,
            about LONGTEXT
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('wcf1_user_option_value')
            ->orderBy('userID')
            ->chunk(200, function ($options): void {
                $insertData = [];
                foreach ($options as $option) {
                    $insertData[] = [
                        'userID' => (int) $option->userID,
                        'about' => $option->userOption1,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_user_options_values')->insert($insertData);
                }
            });
    }

    /**
     * Bring the file authors from the Hub database to the local database temporary table.
     */
    protected function bringFileAuthorsLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_author');
        DB::statement('CREATE TEMPORARY TABLE temp_file_author (
            fileID INT,
            userID INT
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('filebase1_file_author')
            ->orderBy('fileID')
            ->chunk(200, function ($relationships): void {
                $insertData = [];
                foreach ($relationships as $relationship) {
                    $insertData[] = [
                        'fileID' => (int) $relationship->fileID,
                        'userID' => (int) $relationship->userID,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_file_author')->insert($insertData);
                }
            });
    }

    /**
     * Bring the file options from the Hub database to the local database temporary table.
     */
    protected function bringFileOptionsLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_option_values');
        DB::statement('CREATE TEMPORARY TABLE temp_file_option_values (
            fileID INT,
            optionID INT,
            optionValue VARCHAR(255)
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('filebase1_file_option_value')
            ->orderBy('fileID')
            ->chunk(200, function ($options): void {
                $insertData = [];
                foreach ($options as $option) {
                    $insertData[] = [
                        'fileID' => (int) $option->fileID,
                        'optionID' => (int) $option->optionID,
                        'optionValue' => $option->optionValue,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_file_option_values')->insert($insertData);
                }
            });
    }

    /**
     * Bring the file content from the Hub database to the local database temporary table.
     */
    protected function bringFileContentLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_content');
        DB::statement('CREATE TEMPORARY TABLE temp_file_content (
            fileID INT,
            subject VARCHAR(255),
            teaser VARCHAR(255),
            message LONGTEXT
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('filebase1_file_content')
            ->orderBy('fileID')
            ->chunk(200, function ($contents): void {
                $insertData = [];
                foreach ($contents as $content) {
                    $insertData[] = [
                        'fileID' => (int) $content->fileID,
                        'subject' => $content->subject,
                        'teaser' => $content->teaser,
                        'message' => $content->message,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_file_content')->insert($insertData);
                }
            });
    }

    /**
     * Bring the file version labels from the Hub database to the local database temporary table.
     */
    protected function bringFileVersionLabelsLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_version_labels');
        DB::statement('CREATE TEMPORARY TABLE temp_file_version_labels (
            labelID INT,
            objectID INT
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('wcf1_label_object')
            ->where('objectTypeID', 387)
            ->orderBy('labelID')
            ->chunk(200, function ($options): void {
                $insertData = [];
                foreach ($options as $option) {
                    $insertData[] = [
                        'labelID' => (int) $option->labelID,
                        'objectID' => (int) $option->objectID,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_file_version_labels')->insert($insertData);
                }
            });
    }

    /**
     * Bring the file version content from the Hub database to the local database temporary table.
     */
    protected function bringFileVersionContentLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_file_version_content');
        DB::statement('CREATE TEMPORARY TABLE temp_file_version_content (
            versionID INT,
            description TEXT
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('filebase1_file_version_content')
            ->orderBy('versionID')
            ->chunk(200, function ($options): void {
                $insertData = [];
                foreach ($options as $option) {
                    $insertData[] = [
                        'versionID' => (int) $option->versionID,
                        'description' => $option->description,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_file_version_content')->insert($insertData);
                }
            });
    }

    private function bringSptVersionTagsLocal(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS temp_spt_version_tags');
        DB::statement('CREATE TEMPORARY TABLE temp_spt_version_tags (
            hub_id INT,
            version VARCHAR(255),
            color_class VARCHAR(255)
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');

        DB::connection('mysql_hub')
            ->table('wcf1_label')
            ->where('groupID', 1)
            ->orderBy('labelID')
            ->chunk(100, function (Collection $versions): void {
                $insertData = [];
                foreach ($versions as $version) {
                    $insertData[] = [
                        'hub_id' => (int) $version->labelID,
                        'version' => $version->label,
                        'color_class' => $version->cssClassName,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('temp_spt_version_tags')->insert($insertData);
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
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
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
            ->chunkById(250, function (Collection $users) use ($curl): void {
                $userData = [];
                $bannedUsers = [];
                $userRanks = [];
                foreach ($users as $user) {
                    $hubUser = new HubUser(
                        $user->userID,
                        $user->username,
                        $user->email,
                        $user->password,
                        $user->registrationDate,
                        $user->banned,
                        $user->banReason,
                        $user->banExpires,
                        $user->coverPhotoHash,
                        $user->coverPhotoExtension,
                        $user->rankID,
                        $user->rankTitle
                    );

                    $userData[] = $this->collectUserData($curl, $hubUser);

                    $bannedUserData = $this->collectBannedUserData($hubUser);
                    if ($bannedUserData) {
                        $bannedUsers[] = $bannedUserData;
                    }

                    $userRankData = $this->collectUserRankData($hubUser);
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

    /**
     * Build an array of user data ready to be inserted into the local database.
     *
     * @return array<string, mixed>
     */
    protected function collectUserData(CurlHandle $curlHandle, HubUser $hubUser): array
    {
        return [
            'hub_id' => $hubUser->userID,
            'name' => $hubUser->username,
            'email' => Str::lower($hubUser->email),
            'password' => $this->cleanPasswordHash($hubUser->password),
            'about' => $this->fetchUserAbout($hubUser->userID),
            'profile_photo_path' => $this->fetchUserAvatar($curlHandle, $hubUser),
            'cover_photo_path' => $this->fetchUserCoverPhoto($curlHandle, $hubUser),
            'created_at' => $this->cleanRegistrationDate($hubUser->registrationDate),
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
     * Fetch the user about text from the temporary table.
     */
    private function fetchUserAbout(int $userID): string
    {
        $about = DB::table('temp_user_options_values')
            ->where('userID', $userID)
            ->limit(1)
            ->value('about');

        return $this->cleanHubContent($about ?? '');
    }

    /**
     * Convert the mod description from WoltHub flavoured HTML to Markdown.
     */
    protected function cleanHubContent(string $dirty): string
    {
        // Alright, hear me out... Shut up.

        $htmlConverter = new HtmlConverter;
        $clean = Purify::clean($dirty);

        return $htmlConverter->convert($clean);
    }

    /**
     * Fetch the user avatar from the Hub and store it anew.
     */
    protected function fetchUserAvatar(CurlHandle $curlHandle, HubUser $hubUser): string
    {
        // Fetch the user's avatar data from the temporary table.
        $avatar = DB::table('temp_user_avatar')->where('userID', $hubUser->userID)->first();

        if (! $avatar) {
            return '';
        }

        $hashShort = substr((string) $avatar->fileHash, 0, 2);
        $fileName = $avatar->fileHash.'.'.$avatar->avatarExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/images/avatars/'.$hashShort.'/'.$avatar->avatarID.'-'.$fileName;
        $relativePath = User::profilePhotoStoragePath().'/'.$fileName;

        return $this->fetchAndStoreImage($curlHandle, $hubUrl, $relativePath);
    }

    /**
     * Fetch and store an image from the Hub.
     */
    protected function fetchAndStoreImage(CurlHandle $curlHandle, string $hubUrl, string $relativePath): string
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
        curl_setopt($curlHandle, CURLOPT_URL, $hubUrl);
        $image = curl_exec($curlHandle);

        if ($image === false) {
            Log::error('There was an error attempting to download the image. cURL error: '.curl_error($curlHandle));

            return '';
        }

        // Store the image on the disk.
        Storage::disk($disk)->put($relativePath, $image);

        return $relativePath;
    }

    /**
     * Fetch the user avatar from the Hub and store it anew.
     */
    protected function fetchUserCoverPhoto(CurlHandle $curlHandle, HubUser $hubUser): string
    {
        if ($hubUser->coverPhotoHash === null || $hubUser->coverPhotoHash === '' || $hubUser->coverPhotoExtension === null || $hubUser->coverPhotoExtension === '') {
            return '';
        }

        $hashShort = substr($hubUser->coverPhotoHash, 0, 2);
        $fileName = $hubUser->coverPhotoHash.'.'.$hubUser->coverPhotoExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/images/coverPhotos/'.$hashShort.'/'.$hubUser->userID.'-'.$fileName;
        $relativePath = 'user-covers/'.$fileName;

        return $this->fetchAndStoreImage($curlHandle, $hubUrl, $relativePath);
    }

    /**
     * Clean the registration date from the Hub database.
     */
    protected function cleanRegistrationDate(int $registrationDate): string
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
     *
     * @return array<string, mixed>|null
     */
    protected function collectBannedUserData(HubUser $hubUser): ?array
    {
        if ($hubUser->banned) {
            return [
                'hub_id' => $hubUser->userID,
                'comment' => $hubUser->banReason ?? '',
                'expired_at' => $this->cleanUnbannedAtDate($hubUser->banExpires),
            ];
        }

        return null;
    }

    /**
     * Clean the banned_at date from the Hub database.
     */
    protected function cleanUnbannedAtDate(?int $unbannedAt): ?string
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
        } catch (Exception) {
            // If the date is not valid, return null
            return null;
        }
    }

    /**
     * Build an array of user rank data ready to be inserted into the local database.
     *
     * @return array<string, mixed>|null
     */
    protected function collectUserRankData(HubUser $hubUser): ?array
    {
        if ($hubUser->rankID && $hubUser->rankTitle) {
            return [
                'hub_id' => $hubUser->userID,
                'title' => $hubUser->rankTitle,
            ];
        }

        return null;
    }

    /**
     * Insert or update the users in the local database.
     *
     * @param  array<array<string, mixed>>  $usersData
     */
    protected function upsertUsers(array $usersData): void
    {
        if ($usersData !== []) {
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
     *
     * @param  array<array<string, mixed>>  $bannedUsers
     */
    protected function handleBannedUsers(array $bannedUsers): void
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
     *
     * @param  array<array<string, mixed>>  $userRanks
     */
    protected function handleUserRoles(array $userRanks): void
    {
        foreach ($userRanks as $userRank) {
            $roleName = Str::ucfirst(Str::afterLast($userRank['title'], '.'));
            $roleData = $this->buildUserRoleData($roleName);
            UserRole::query()->upsert($roleData, ['name'], ['name', 'short_name', 'description', 'color_class']);

            $userRole = UserRole::whereName($roleData['name'])->first();
            $user = User::whereHubId($userRank['hub_id'])->first();
            $user->assignRole($userRole);
        }
    }

    /**
     * Build the user role data based on the role name.
     *
     * @return array<string, string>
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

    protected function importUserFollows(): void
    {
        $followsGroupedByFollower = [];

        DB::connection('mysql_hub')
            ->table('wcf1_user_follow')
            ->select(['followID', 'userID', 'followUserID', 'time'])
            ->chunkById(100, function (Collection $follows) use (&$followsGroupedByFollower): void {
                foreach ($follows as $follow) {
                    $followerId = User::whereHubId($follow->userID)->value('id');
                    $followingId = User::whereHubId($follow->followUserID)->value('id');

                    if (! $followerId || ! $followingId) {
                        continue;
                    }

                    $followsGroupedByFollower[$followerId][$followingId] = [
                        'created_at' => Carbon::parse($follow->time, 'UTC'),
                        'updated_at' => Carbon::parse($follow->time, 'UTC'),
                    ];
                }
            }, 'followID');

        foreach ($followsGroupedByFollower as $followerId => $followings) {
            $user = User::query()->find($followerId);
            if ($user) {
                $user->following()->sync($followings);
            }
        }
    }

    /**
     * Import the licenses from the Hub database to the local database.
     */
    protected function importLicenses(): void
    {
        DB::connection('mysql_hub')
            ->table('filebase1_license')
            ->chunkById(100, function (Collection $licenses): void {

                $insertData = [];
                foreach ($licenses as $license) {
                    $insertData[] = [
                        'hub_id' => (int) $license->licenseID,
                        'name' => $license->licenseName,
                        'link' => $license->licenseURL,
                    ];
                }

                if ($insertData !== []) {
                    DB::table('licenses')->upsert($insertData, ['hub_id'], ['name', 'link']);
                }
            }, 'licenseID');
    }

    /**
     * Import the SPT versions from the public GitHub repo to the local database.
     *
     * @throws Exception
     */
    protected function importSptVersions(): void
    {
        $url = 'https://api.github.com/repos/sp-tarkov/build/releases';
        $token = config('services.github.token');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: The Forge (forge.sp-tarkov.com)',
            'Authorization: token '.$token,
        ]);

        $response = curl_exec($ch);

        throw_if(curl_errno($ch) !== 0, new Exception('cURL Error: '.curl_error($ch)));

        curl_close($ch);

        $response = (array) json_decode($response, true);

        throw_if(json_last_error() !== JSON_ERROR_NONE, new Exception('JSON Decode Error: '.json_last_error_msg()));

        throw_if($response === [], new Exception('No version data found in the GitHub API response.'));

        // Filter out drafts and pre-releases.
        $response = array_filter($response, fn (array $release): bool => ! $release['draft'] && ! $release['prerelease']);

        throw_if($response === [], new Exception('No finalized versions found after filtering drafts and pre-releases.'));

        // Ensure that each of the tag_name values has any 'v' prefix trimmed.
        $response = array_map(function (array $release) {
            $release['tag_name'] = Str::of($release['tag_name'])->ltrim('v')->toString();

            return $release;
        }, $response);

        $latestVersion = $this->getLatestVersion($response);

        $insertData = [];
        foreach ($response as $version) {
            $insertData[] = [
                'version' => $version['tag_name'],
                'link' => $version['html_url'],
                'color_class' => $this->detectVersionColor($version['tag_name'], $latestVersion),
                'created_at' => Carbon::parse($version['published_at'], 'UTC'),
                'updated_at' => Carbon::parse($version['published_at'], 'UTC'),
            ];
        }

        // Add a fake 0.0.0 version for outdated mods.
        $insertData[] = [
            'version' => '0.0.0',
            'link' => '',
            'color_class' => 'black',
            'created_at' => Carbon::now('UTC'),
            'updated_at' => Carbon::now('UTC'),
        ];

        // Manually update or create
        foreach ($insertData as $data) {
            $existingVersion = SptVersion::query()->where('version', $data['version'])->first();
            if ($existingVersion) {
                $existingVersion->update([
                    'link' => $data['link'],
                    'color_class' => $data['color_class'],
                    'created_at' => $data['created_at'],
                    'updated_at' => $data['updated_at'],
                ]);
            } else {
                SptVersion::query()->create($data);
            }
        }
    }

    /**
     * Get the latest current version from the response data.
     *
     * @param  array<array<string, mixed>>  $versions
     */
    protected function getLatestVersion(array $versions): string
    {
        $semanticVersions = array_map(
            fn ($version): ?string => $this->extractSemanticVersion($version['tag_name']),
            $versions
        );

        usort($semanticVersions, 'version_compare');

        return end($semanticVersions);
    }

    /**
     * Extract the last semantic version from a string.
     * If the version has no patch number, return it as `~<major>.<minor>.0`.
     */
    protected function extractSemanticVersion(string $versionString, bool $appendPatch = false): ?string
    {
        // Match both two-part and three-part semantic versions
        preg_match_all('/\b\d+\.\d+(?:\.\d+)?\b/', $versionString, $matches);

        // Get the last version found, if any
        $version = end($matches[0]) ?: null;

        if (! $appendPatch) {
            return $version;
        }

        // If version is two-part (e.g., "3.9"), add ".0" and prefix with "~"
        if ($version && preg_match('/^\d+\.\d+$/', $version)) {
            $version = '~'.$version.'.0';
        }

        return $version;
    }

    /**
     * Translate the version string into a color class.
     */
    protected function detectVersionColor(string $versionString, string $currentVersion): string
    {
        $version = $this->extractSemanticVersion($versionString);
        if (! $version) {
            return 'gray';
        }

        if ($version === '0.0.0') {
            return 'black';
        }

        [$currentMajor, $currentMinor] = explode('.', $currentVersion);
        [$major, $minor] = explode('.', $version);

        $currentMajor = (int) $currentMajor;
        $currentMinor = (int) $currentMinor;
        $major = (int) $major;
        $minor = (int) $minor;

        if ($major === $currentMajor) {
            $difference = $currentMinor - $minor;

            return match ($difference) {
                0 => 'green',
                1 => 'lime',
                2 => 'yellow',
                3 => 'red',
                default => 'gray',
            };
        }

        return 'gray';
    }

    /**
     * Import the mods from the Hub database to the local database.
     */
    protected function importMods(): void
    {
        // Initialize a cURL handler for downloading mod thumbnails.
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        DB::connection('mysql_hub')
            ->table('filebase1_file')
            ->chunkById(100, function (Collection $mods) use ($curl): void {

                foreach ($mods as $mod) {
                    // Fetch any additional authors for the mod.
                    $modAuthors = DB::table('temp_file_author')
                        ->where('fileID', $mod->fileID)
                        ->pluck('userID')
                        ->toArray();
                    $modAuthors[] = $mod->userID; // Add the primary author to the list.
                    $modAuthors = User::query()->whereIn('hub_id', $modAuthors)->pluck('id')->toArray(); // Replace with local IDs.

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
                        'name' => $modContent->subject ?? '',
                        'slug' => Str::slug($modContent->subject ?? ''),
                        'teaser' => Str::limit($modContent->teaser ?? '', 255),
                        'description' => $this->cleanHubContent($modContent->message ?? ''),
                        'thumbnail' => $this->fetchModThumbnail($curl, $mod->fileID, $mod->iconHash, $mod->iconExtension),
                        'license_id' => License::whereHubId($mod->licenseID)->value('id'),
                        'source_code_link' => $optionSourceCode->source_code_link ?? '',
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

                    Mod::query()->withoutGlobalScopes()->upsert($insertModData, ['hub_id'], [
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
     * Fetch the mod thumbnail from the Hub and store it anew.
     */
    protected function fetchModThumbnail(CurlHandle $curlHandle, int $fileID, string $thumbnailHash, string $thumbnailExtension): string
    {
        // If any of the required fields are empty, return an empty string.
        if (empty($fileID) || ($thumbnailHash === '' || $thumbnailHash === '0') || ($thumbnailExtension === '' || $thumbnailExtension === '0')) {
            return '';
        }

        // Build some paths/URLs using the mod data.
        $hashShort = substr($thumbnailHash, 0, 2);
        $fileName = $fileID.'.'.$thumbnailExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/files/images/file/'.$hashShort.'/'.$fileName;
        $relativePath = 'mods/'.$fileName;

        return $this->fetchAndStoreImage($curlHandle, $hubUrl, $relativePath);
    }

    /**
     * Import the mod versions from the Hub database to the local database.
     */
    protected function importModVersions(): void
    {
        DB::connection('mysql_hub')
            ->table('filebase1_file_version')
            ->chunkById(500, function (Collection $versions): void {

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

                    // Fetch the version string using the labelID from the hub.
                    $sptVersionTemp = DB::table('temp_spt_version_tags')->where('hub_id', $versionLabel->labelID)->value('version');
                    $sptVersionConstraint = $this->extractSemanticVersion($sptVersionTemp, appendPatch: true) ?? '0.0.0';

                    try {
                        $modVersion = new Version($version->versionNumber);
                    } catch (InvalidVersionNumberException) {
                        $modVersion = new Version('0.0.0');
                    }

                    $insertData[] = [
                        'hub_id' => (int) $version->versionID,
                        'mod_id' => $modId,
                        'version' => $modVersion,
                        'version_major' => $modVersion->getMajor(),
                        'version_minor' => $modVersion->getMinor(),
                        'version_patch' => $modVersion->getPatch(),
                        'version_pre_release' => $modVersion->getPreRelease(),
                        'description' => $this->cleanHubContent($versionContent->description ?? ''),
                        'link' => $version->downloadURL,
                        'spt_version_constraint' => $sptVersionConstraint,
                        'virus_total_link' => $optionVirusTotal->virus_total_link ?? '',
                        'downloads' => max((int) $version->downloads, 0), // At least 0.
                        'disabled' => (bool) $version->isDisabled,
                        'published_at' => $sptVersionConstraint === '0.0.0' ? null : Carbon::parse($version->uploadTime, 'UTC'),
                        'created_at' => Carbon::parse($version->uploadTime, 'UTC'),
                        'updated_at' => Carbon::parse($version->uploadTime, 'UTC'),
                    ];
                }

                if (! empty($insertData)) {
                    ModVersion::query()->withoutGlobalScopes()->upsert($insertData, ['hub_id'], [
                        'mod_id',
                        'version',
                        'description',
                        'link',
                        'spt_version_constraint',
                        'virus_total_link',
                        'downloads',
                        'disabled',
                        'published_at',
                        'created_at',
                        'updated_at',
                    ]);
                }
            }, 'versionID');
    }

    /**
     * Remove mods that are no longer on the Hub.
     */
    private function removeDeletedMods(): void
    {
        $mods = Mod::query()->select('hub_id')->get();
        foreach ($mods as $mod) {
            if (DB::connection('mysql_hub')->table('filebase1_file')->where('fileID', $mod->hub_id)->doesntExist()) {
                $mod->delete();
            }
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(Throwable $throwable): void
    {
        // Explicitly drop the temporary tables.
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_user_avatar');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_user_options_values');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_author');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_option_values');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_content');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_version_labels');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_file_version_content');
        DB::unprepared('DROP TEMPORARY TABLE IF EXISTS temp_spt_version_tags');

        // Close the connections. This should drop the temporary tables as well, but I like to be explicit.
        DB::connection('mysql_hub')->disconnect();
        DB::disconnect();
    }
}
