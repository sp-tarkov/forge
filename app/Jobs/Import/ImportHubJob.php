<?php

declare(strict_types=1);

namespace App\Jobs\Import;

use App\Exceptions\InvalidVersionNumberException;
use App\Jobs\Import\DataTransferObjects\GitHubSptVersion;
use App\Jobs\Import\DataTransferObjects\HubMod;
use App\Jobs\Import\DataTransferObjects\HubModLicense;
use App\Jobs\Import\DataTransferObjects\HubModVersion;
use App\Jobs\Import\DataTransferObjects\HubUser;
use App\Jobs\Import\DataTransferObjects\HubUserAvatar;
use App\Jobs\Import\DataTransferObjects\HubUserFollow;
use App\Jobs\Import\DataTransferObjects\HubUserOptionValue;
use App\Jobs\ResolveDependenciesJob;
use App\Jobs\ResolveSptVersionsJob;
use App\Jobs\SearchSyncJob;
use App\Jobs\SptVersionModCountsJob;
use App\Jobs\UpdateModDownloadsJob;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Version;
use Composer\Semver\Semver;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickDraw;
use ImagickDrawException;
use ImagickException;
use ImagickPixel;

class ImportHubJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Accumulator for SPT version constraints across batches.
     *
     * @var array<int, array<int, string>>
     */
    private array $sptVersionConstraints = [];

    /**
     * @throws ConnectionException|RequestException|InvalidVersionNumberException
     */
    public function handle(): void
    {
        // Increase the memory limit for this job.
        // This should also be set in the Horizon config for the Long queue.
        ini_set('memory_limit', '720M');

        $this->getHubUsers();
        $this->getHubUserOptions();
        $this->getHubUserAvatars();
        $this->getHubUserCoverPhotos();
        $this->getHubUserFollows();
        $this->getHubModLicenses();
        $this->getGitHubSptVersions();
        $this->getHubMods();
        $this->getHubModVersions();
        $this->removeDeletedHubMods();
        $this->removeModsWithoutHubVersions();

        Bus::chain([
            (new ResolveSptVersionsJob)->onQueue('long'),
            new ResolveDependenciesJob,
            new SptVersionModCountsJob,
            new UpdateModDownloadsJob,
            (new SearchSyncJob)->onQueue('long')->delay(Carbon::now()->addSeconds(30)),
            fn () => Artisan::call('cache:clear'),
        ])->dispatch();
    }

    /**
     * Get all users from the Hub database and pass them in batches to be processed.
     **/
    private function getHubUsers(): void
    {
        /** @var EloquentCollection<string, UserRole> $roles */
        $roles = UserRole::all()->keyBy('name');

        DB::connection('hub')
            ->table('wcf1_user as u')
            ->select('u.*', 'r.rankTitle')
            ->leftJoin('wcf1_user_rank as r', 'u.rankID', '=', 'r.rankID')
            ->orderBy('u.userID')
            ->chunk(4000, function (Collection $records) use ($roles): void {
                /** @var Collection<int, object> $records */

                /** @var Collection<int, HubUser> $hubUsers */
                $hubUsers = $records->map(fn (object $record): HubUser => HubUser::fromArray((array) $record));

                $localUsers = $this->processUserBatch($hubUsers);
                $this->processUserBatchBans($hubUsers, $localUsers);
                $this->processUserBatchRoles($hubUsers, $localUsers, $roles);
            });
    }

    /**
     * Process a batch of Hub users.
     *
     * @param  Collection<int, HubUser>  $hubUsers
     * @return EloquentCollection<int, User>
     */
    private function processUserBatch(Collection $hubUsers): EloquentCollection
    {
        // Prepare data for upsert.
        $userData = $hubUsers->map(fn (HubUser $hubUser): array => [
            'hub_id' => $hubUser->userID,
            'name' => $hubUser->username,
            'email' => $hubUser->getEmail(),
            'password' => $hubUser->getPassword(),
            'created_at' => $hubUser->getRegistrationDate(),
            'updated_at' => Carbon::now('UTC')->toDateTimeString(),
        ])->toArray();

        // Split upsert into separate insert/update operations to avoid ID gaps
        User::withoutEvents(function () use ($userData): void {
            // Get existing hub_ids to determine which records to update vs insert
            $hubIds = collect($userData)->pluck('hub_id');
            $existingUsers = User::query()->whereIn('hub_id', $hubIds)->pluck('hub_id')->toArray();

            // Split data into inserts (new) and updates (existing)
            $insertData = [];
            $updateData = [];

            foreach ($userData as $user) {
                if (in_array($user['hub_id'], $existingUsers)) {
                    $updateData[] = $user;
                } else {
                    $insertData[] = $user;
                }
            }

            // Insert new users (only allocates IDs for actual new records)
            if (! empty($insertData)) {
                User::query()->insert($insertData);
            }

            // Update existing users (no ID allocation)
            foreach ($updateData as $user) {
                User::query()->where('hub_id', $user['hub_id'])->update([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => $user['password'],
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['updated_at'],
                ]);
            }
        });

        // Fetch and return the up-to-date local users
        $hubUserIds = $hubUsers->pluck('userID');

        /** @var EloquentCollection<int, User> $localUsers */
        $localUsers = User::query()->whereIn('hub_id', $hubUserIds)->get()->keyBy('hub_id');

        return $localUsers;
    }

    /**
     * Process a batch of Hub users and ban them if necessary.
     *
     * @param  Collection<int, HubUser>  $hubUsers
     * @param  EloquentCollection<int, User>  $localUsers
     */
    private function processUserBatchBans(Collection $hubUsers, EloquentCollection $localUsers): void
    {
        $hubUsers->each(function (HubUser $hubUser) use ($localUsers): void {
            if ($hubUser->banned > 0 && ($user = $localUsers->get($hubUser->userID))) {
                if ($user->isNotBanned()) {
                    $user->ban([
                        'comment' => $hubUser->banReason ?? '',
                        'expired_at' => $hubUser->getBanExpires(),
                    ]);
                }
            }
        });
    }

    /**
     * Process a batch of Hub users and add their user roles.
     *
     * @param  Collection<int, HubUser>  $hubUsers
     * @param  EloquentCollection<int, User>  $localUsers
     * @param  EloquentCollection<string, UserRole>  $roles
     */
    private function processUserBatchRoles(Collection $hubUsers, EloquentCollection $localUsers, EloquentCollection $roles): void
    {
        $hubUsers->each(function (HubUser $hubUser) use ($localUsers, $roles): void {
            if ($hubUser->banned) {
                return;
            }

            $rankTitle = $hubUser->getRankTitle();

            if (! empty($hubUser->rankID) && $rankTitle && ($user = $localUsers->get($hubUser->userID))) {
                $role = $roles->get($rankTitle);

                // If the role is not found in pre-fetched data, then create it.
                if (! $role) {
                    $role = UserRole::query()->firstOrCreate(['name' => $rankTitle], match ($rankTitle) {
                        'Administrator' => [
                            'short_name' => 'Admin',
                            'description' => 'Full access',
                            'color_class' => 'sky',
                        ],
                        'Moderator' => [
                            'short_name' => 'Mod',
                            'description' => 'Moderate user content.',
                            'color_class' => 'emerald',
                        ],
                        default => [
                            'short_name' => '',
                            'description' => '',
                            'color_class' => '',
                        ],
                    });

                    // Add the created role to our collection for this batch.
                    $roles->put($rankTitle, $role);
                }

                $user->assignRole($role);
            }
        });
    }

    /**
     * Get all user options from the Hub database and pass them in batches to be processed.
     */
    private function getHubUserOptions(): void
    {
        // Currently, we're only getting rows where the about text has value.
        DB::connection('hub')
            ->table('wcf1_user_option_value')
            ->whereNotNull('userOption1') // About field
            ->whereNot('userOption1', value: '')
            ->orderBy('userID')
            ->chunk(5000, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubUserOptionValue> $hubUserOptionValues */
                $hubUserOptionValues = $records->map(fn (object $record): HubUserOptionValue => HubUserOptionValue::fromArray((array) $record));
                $hubUserIds = $hubUserOptionValues->pluck('userID');

                /** @var EloquentCollection<int, User> $localUsers */
                $localUsers = User::query()->whereIn('hub_id', $hubUserIds)->get()->keyBy('hub_id');

                $this->processUserOptionValueBatch($hubUserOptionValues, $localUsers);
            });
    }

    /**
     * Process a batch of Hub user option values.
     *
     * @param  Collection<int, HubUserOptionValue>  $hubUserOptionValues
     * @param  EloquentCollection<int, User>  $localUsers
     */
    private function processUserOptionValueBatch(Collection $hubUserOptionValues, EloquentCollection $localUsers): void
    {
        $now = Carbon::now('UTC')->toDateTimeString();

        User::withoutEvents(function () use ($hubUserOptionValues, $localUsers, $now): void {
            $hubUserOptionValues
                ->filter(fn (HubUserOptionValue $hubUserOptionValue): bool => $hubUserOptionValue->getAbout() !== '')
                ->each(function (HubUserOptionValue $hubUserOptionValue) use ($localUsers, $now): void {
                    if ($localUser = $localUsers->get($hubUserOptionValue->userID)) {
                        User::query()
                            ->where('id', $localUser->id)
                            ->update([
                                'about' => $hubUserOptionValue->getAbout(),
                                'updated_at' => $now,
                            ]);
                    }
                });
        });
    }

    /**
     * Get all user avatars from the Hub database and pass them in batches to be processed.
     *
     * @throws ConnectionException
     */
    private function getHubUserAvatars(): void
    {
        DB::connection('hub')
            ->table('wcf1_user_avatar')
            ->orderBy('userID')
            ->chunk(5000, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubUserAvatar> $hubUserAvatars */
                $hubUserAvatars = $records->map(fn (object $record): HubUserAvatar => HubUserAvatar::fromArray((array) $record));
                $hubUserIds = $hubUserAvatars->pluck('userID')->filter()->unique();

                /** @var EloquentCollection<int, User> $localUsers */
                $localUsers = User::query()->whereIn('hub_id', $hubUserIds)->get()->keyBy('hub_id');

                $this->processUserAvatarBatch($hubUserAvatars, $localUsers);
            });
    }

    /**
     * Process a batch of Hub user avatars.
     *
     * @param  Collection<int, HubUserAvatar>  $hubUserAvatars
     * @param  EloquentCollection<int, User>  $localUsers
     *
     * @throws ConnectionException
     */
    private function processUserAvatarBatch(Collection $hubUserAvatars, EloquentCollection $localUsers): void
    {
        $now = Carbon::now('UTC')->toDateTimeString();

        User::withoutEvents(function () use ($hubUserAvatars, $localUsers, $now): void {
            $hubUserAvatars->each(function (HubUserAvatar $hubUserAvatar) use ($localUsers, $now): void {
                if ($hubUserAvatar->userID && ($localUser = $localUsers->get($hubUserAvatar->userID))) {
                    $relativePath = $this->processUserAvatarImage($hubUserAvatar);
                    if (! empty($relativePath)) {
                        User::query()
                            ->where('id', $localUser->id)
                            ->update([
                                'profile_photo_path' => $relativePath,
                                'updated_at' => $now,
                            ]);
                    }
                }
            });
        });
    }

    /**
     * Process/download a user avatar image.
     *
     * @throws ConnectionException
     */
    private function processUserAvatarImage(HubUserAvatar $avatar): string
    {
        // Build the URL based on the avatar data.
        $hashShort = substr($avatar->fileHash, 0, 2);
        $fileName = $avatar->fileHash.'.'.$avatar->avatarExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/images/avatars/'.$hashShort.'/'.$avatar->avatarID.'-'.$fileName;
        $relativePath = User::profilePhotoStoragePath().'/'.$fileName;

        return self::fetchAndStoreImage($hubUrl, $relativePath);
    }

    /**
     * Get all user cover photos from the Hub database and pass them in batches to be processed.
     *
     * @throws ConnectionException
     */
    private function getHubUserCoverPhotos(): void
    {
        DB::connection('hub')
            ->table('wcf1_user')
            ->whereNotNull('coverPhotoHash')
            ->whereNot('coverPhotoHash', value: '')
            ->whereNot('coverPhotoExtension', value: '')
            ->orderBy('userID')
            ->chunk(5000, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubUser> $hubUsers */
                $hubUsers = $records->map(fn (object $record): HubUser => HubUser::fromArray((array) $record));
                $hubUserIds = $hubUsers->pluck('userID');

                /** @var EloquentCollection<int, User> $localUsers */
                $localUsers = User::query()->whereIn('hub_id', $hubUserIds)->get()->keyBy('hub_id');

                $this->processUserCoverPhotoBatch($hubUsers, $localUsers);
            });
    }

    /**
     * Process a batch of cover photos for Hub users.
     *
     * @param  Collection<int, HubUser>  $hubUsers
     * @param  EloquentCollection<int, User>  $localUsers
     *
     * @throws ConnectionException
     */
    private function processUserCoverPhotoBatch(Collection $hubUsers, EloquentCollection $localUsers): void
    {
        $now = Carbon::now('UTC')->toDateTimeString();

        User::withoutEvents(function () use ($hubUsers, $localUsers, $now): void {
            $hubUsers->each(function (HubUser $hubUser) use ($localUsers, $now): void {
                if ($localUser = $localUsers->get($hubUser->userID)) {
                    $coverPhotoPath = $this->fetchUserCoverPhoto($hubUser);
                    if (! empty($coverPhotoPath)) {
                        User::query()
                            ->where('id', $localUser->id)
                            ->update([
                                'cover_photo_path' => $coverPhotoPath,
                                'updated_at' => $now,
                            ]);
                    }
                }
            });
        });
    }

    /**
     * Fetch the user cover photo from the Hub and store it.
     *
     * @throws ConnectionException
     */
    private function fetchUserCoverPhoto(HubUser $hubUser): string
    {
        $fileName = $hubUser->getCoverPhotoFileName();
        if (empty($fileName)) {
            return '';
        }

        $hashShort = substr((string) $hubUser->coverPhotoHash, 0, 2);
        $hubUrl = 'https://hub.sp-tarkov.com/images/coverPhotos/'.$hashShort.'/'.$hubUser->userID.'-'.$fileName;
        $relativePath = 'cover-photos/'.$fileName;

        return $this->fetchAndStoreImage($hubUrl, $relativePath);
    }

    /**
     * Process/download and store an image from the given URL.
     *
     * @throws ConnectionException
     */
    private function fetchAndStoreImage(string $hubUrl, string $relativePath, bool $forceUpdate = false): string
    {
        // Determine the disk to use based on the environment.
        $disk = match (config('app.env')) {
            'production' => 'r2', // Cloudflare R2 Storage
            default => 'public',  // Local storage
        };

        // If the image already exists, and we're not forcing an update, return its path.
        if (! $forceUpdate && Storage::disk($disk)->exists($relativePath)) {
            return $relativePath;
        }

        $response = Http::get($hubUrl);

        if ($response->failed()) {
            Log::error('There was an error attempting to download the image. HTTP error: '.$response->status());

            return '';
        }

        // Store the image on the selected disk.
        Storage::disk($disk)->put($relativePath, $response->body());
        unset($response);

        return $relativePath;
    }

    /**
     * Get all user follows from the Hub database.
     **/
    private function getHubUserFollows(): void
    {
        /** @var array<int, array<int, array{created_at: Carbon, updated_at: Carbon}>> $followsGroupedByFollower */
        $followsGroupedByFollower = [];

        /** @var array<int> $allRelevantHubUserIds */
        $allRelevantHubUserIds = [];

        // Collect all relevant Hub User IDs
        DB::connection('hub')
            ->table('wcf1_user_follow')
            ->orderBy('followID')
            ->chunk(5000, function (Collection $records) use (&$allRelevantHubUserIds): void {
                $records->each(function (object $record) use (&$allRelevantHubUserIds): void {
                    if (isset($record->userID)) {
                        $allRelevantHubUserIds[] = $record->userID;
                    }

                    if (isset($record->followUserID)) {
                        $allRelevantHubUserIds[] = $record->followUserID;
                    }
                });
            });

        // Fetch all relevant local users.
        $uniqueHubUserIds = collect($allRelevantHubUserIds)->filter()->unique()->all();

        /** @var EloquentCollection<int, User> $localUsers */
        $localUsers = User::query()->whereIn('hub_id', $uniqueHubUserIds)->get()->keyBy('hub_id');

        // Process follows using the fetched local users.
        DB::connection('hub')
            ->table('wcf1_user_follow')
            ->orderBy('followID')
            ->chunk(5000, function (Collection $records) use (&$followsGroupedByFollower, $localUsers): void {
                /** @var Collection<int, object> $records */
                $records
                    ->map(fn (object $record): HubUserFollow => HubUserFollow::fromArray((array) $record))
                    ->each(function (HubUserFollow $hubUserFollow) use (&$followsGroupedByFollower, $localUsers): void {
                        $follower = $localUsers->get($hubUserFollow->userID);
                        $following = $localUsers->get($hubUserFollow->followUserID);

                        if (! $follower || ! $following) {
                            return;
                        }

                        $followerId = $follower->id;
                        $followingId = $following->id;

                        $followsGroupedByFollower[$followerId][$followingId] = [
                            'created_at' => Carbon::parse($hubUserFollow->time, 'UTC'),
                            'updated_at' => Carbon::parse($hubUserFollow->time, 'UTC'),
                        ];
                    });
            });

        // Fetch follower Users needed for sync.
        $followerIdsToSync = array_keys($followsGroupedByFollower);

        /** @var EloquentCollection<int, User> $followersToUpdate */
        $followersToUpdate = User::query()->findMany($followerIdsToSync)->keyBy('id');

        User::withoutEvents(function () use ($followersToUpdate, $followsGroupedByFollower): void {
            foreach ($followsGroupedByFollower as $followerId => $followings) {
                if ($user = $followersToUpdate->get($followerId)) {
                    $user->following()->syncWithoutDetaching($followings); // Don't remove existing relationships.
                }
            }
        });
    }

    /**
     * Get all mod licenses from the Hub database and pass them in batches to be processed.
     **/
    private function getHubModLicenses(): void
    {
        DB::connection('hub')
            ->table('filebase1_license')
            ->orderBy('licenseID')
            ->chunk(5000, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubModLicense> $hubModLicenses */
                $hubModLicenses = $records->map(fn (object $record): HubModLicense => HubModLicense::fromArray((array) $record));

                $this->processModLicenseBatch($hubModLicenses);
            });
    }

    /**
     * Process a batch of Hub mod licenses.
     *
     * @param  Collection<int, HubModLicense>  $hubModLicenses
     */
    private function processModLicenseBatch(Collection $hubModLicenses): void
    {
        $now = Carbon::now('UTC')->toDateTimeString();

        $licenseData = $hubModLicenses->map(fn (HubModLicense $hubModLicense): array => [
            'hub_id' => $hubModLicense->licenseID,
            'name' => $hubModLicense->licenseName,
            'link' => $hubModLicense->licenseURL,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        License::withoutEvents(function () use ($licenseData): void {
            // Get existing hub_ids to determine which records to update vs insert
            $hubIds = collect($licenseData)->pluck('hub_id');
            $existingLicenses = License::query()->whereIn('hub_id', $hubIds)->pluck('hub_id')->toArray();

            // Split data into inserts (new) and updates (existing)
            $insertData = [];
            $updateData = [];

            foreach ($licenseData as $license) {
                if (in_array($license['hub_id'], $existingLicenses)) {
                    $updateData[] = $license;
                } else {
                    $insertData[] = $license;
                }
            }

            // Insert new licenses (only allocates IDs for actual new records)
            if (! empty($insertData)) {
                License::query()->insert($insertData);
            }

            // Update existing licenses (no ID allocation)
            foreach ($updateData as $license) {
                License::query()->where('hub_id', $license['hub_id'])->update([
                    'name' => $license['name'],
                    'link' => $license['link'],
                    'created_at' => $license['created_at'],
                    'updated_at' => $license['updated_at'],
                ]);
            }
        });
    }

    /**
     * Get all SPT versions from the GitHub build repository:
     * https://github.com/sp-tarkov/build/releases
     *
     * @throws ConnectionException|RequestException|InvalidVersionNumberException
     */
    private function getGitHubSptVersions(): void
    {
        $url = 'https://api.github.com/repos/sp-tarkov/build/releases';

        $response = Http::acceptJson()
            ->withUserAgent(Str::slug(config('app.name').'-'.config('app.env').'-'.config('app.url')))
            ->withToken(config('services.github.token'))
            ->get($url);

        $response->throwUnlessStatus(200);

        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json();
        $releases = collect($data);

        /** @var Collection<int, GitHubSptVersion> $gitHubSptReleases */
        $gitHubSptReleases = $releases
            ->map(fn (array $record): GitHubSptVersion => GitHubSptVersion::fromArray($record))
            ->reject(fn (GitHubSptVersion $release): bool => $release->draft || $release->prerelease)
            ->map(function (GitHubSptVersion $release): GitHubSptVersion {
                try {
                    $release->tag_name = Version::cleanSptImport($release->tag_name)->getVersion();
                } catch (InvalidVersionNumberException $invalidVersionNumberException) {
                    Log::warning(sprintf("Invalid SPT version format from GitHub release '%s': %s", $release->tag_name, $invalidVersionNumberException->getMessage()));
                    $release->tag_name = ''; // Filtered out
                }

                return $release;
            })
            ->filter(fn (GitHubSptVersion $release): bool => $release->tag_name !== '');

        $this->processSptVersions($gitHubSptReleases);
    }

    /**
     * Process the SPT versions, inserting them into the database.
     *
     * @param  Collection<int, GitHubSptVersion>  $releases
     */
    private function processSptVersions(Collection $releases): void
    {
        // Sort the releases by the tag_name using Semver::sort
        $sortedVersions = Semver::sort($releases->pluck('tag_name')->toArray());
        $latestVersion = end($sortedVersions);
        $now = Carbon::now('UTC')->toDateTimeString();

        // Ensure a "dummy" version exists so we can resolve outdated mods to it.
        $versionData[] = [
            'version' => '0.0.0',
            'version_major' => 0,
            'version_minor' => 0,
            'version_patch' => 0,
            'version_labels' => '',
            'link' => '',
            'color_class' => 'gray',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $releases->each(function (GitHubSptVersion $release) use ($latestVersion, &$versionData): void {
            try {
                $version = new Version($release->tag_name);
                $publishedAt = Carbon::parse($release->published_at, 'UTC')->toDateTimeString();
                $versionData[] = [
                    'version' => $version->getVersion(),
                    'version_major' => $version->getMajor(),
                    'version_minor' => $version->getMinor(),
                    'version_patch' => $version->getPatch(),
                    'version_labels' => $version->getLabels(),
                    'link' => $release->html_url,
                    'color_class' => self::detectSptVersionColor($release->tag_name, $latestVersion),
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ];
            } catch (InvalidVersionNumberException $invalidVersionNumberException) {
                Log::warning(sprintf("Skipping processing GitHub release '%s' due to version parsing error: %s", $release->tag_name, $invalidVersionNumberException->getMessage()));
            }
        });

        // Split upsert into separate insert/update operations to avoid ID gaps
        SptVersion::withoutEvents(function () use ($versionData): void {
            // Get existing versions to determine which records to update vs insert
            $versions = collect($versionData)->pluck('version');
            $existingVersions = SptVersion::query()->whereIn('version', $versions)->pluck('version')->toArray();

            // Split data into inserts (new) and updates (existing)
            $insertData = [];
            $updateData = [];

            foreach ($versionData as $version) {
                if (in_array($version['version'], $existingVersions)) {
                    $updateData[] = $version;
                } else {
                    $insertData[] = $version;
                }
            }

            // Insert new versions (only allocates IDs for actual new records)
            if (! empty($insertData)) {
                SptVersion::query()->insert($insertData);
            }

            // Update existing versions (no ID allocation)
            foreach ($updateData as $version) {
                SptVersion::query()->where('version', $version['version'])->update([
                    'version_major' => $version['version_major'],
                    'version_minor' => $version['version_minor'],
                    'version_patch' => $version['version_patch'],
                    'version_labels' => $version['version_labels'],
                    'link' => $version['link'],
                    'color_class' => $version['color_class'],
                    'created_at' => $version['created_at'],
                    'updated_at' => $version['updated_at'],
                ]);
            }
        });
    }

    /**
     * Determine the color for the SPT version.
     *
     * @throws InvalidVersionNumberException
     */
    private static function detectSptVersionColor(string $rawVersion, false|string $rawLatestVersion): string
    {
        if ($rawVersion === '0.0.0' || $rawLatestVersion === false) {
            return 'gray';
        }

        $version = new Version($rawVersion);
        $currentMajor = $version->getMajor();
        $currentMinor = $version->getMinor();

        $latestVersion = new Version($rawLatestVersion);
        $latestMajor = $latestVersion->getMajor();
        $latestMinor = $latestVersion->getMinor();

        if ($currentMajor !== $latestMajor) {
            return 'red';
        }

        $minorDifference = $latestMinor - $currentMinor;

        return match ($minorDifference) {
            0 => 'green',
            default => 'red',
        };
    }

    /**
     * Get all mods from the Hub database and pass them in batches to be processed.
     **/
    private function getHubMods(): void
    {
        /** @var EloquentCollection<int, License> $localLicenses */
        $localLicenses = License::all()->keyBy('hub_id');

        DB::connection('hub')
            ->table('filebase1_file as file')
            ->select(
                DB::raw('file.fileID as fileIDChunkKey'),
                'file.*',
                DB::raw('ANY_VALUE(content.subject) AS subject'),
                DB::raw('ANY_VALUE(content.teaser) AS teaser'),
                DB::raw('ANY_VALUE(content.message) AS message'),
                DB::raw("IFNULL(GROUP_CONCAT(TRIM(additionalAuthors.userID) ORDER BY additionalAuthors.userID SEPARATOR ','), '') AS additional_authors"),
                DB::raw("IFNULL(GROUP_CONCAT(TRIM(optionSourceCode.optionValue) ORDER BY optionSourceCode.optionValue SEPARATOR ','), '') AS source_code_url"),
                DB::raw('IFNULL(ANY_VALUE(optionContainsAI.optionValue), 0) AS contains_ai'),
                DB::raw('IFNULL(ANY_VALUE(optionContainsAds.optionValue), 0) AS contains_ads'),
                DB::raw("IFNULL(ANY_VALUE(spt_version.label), '') AS spt_version_label"),
            )
            ->leftJoin('filebase1_file_author as additionalAuthors', 'file.fileID', '=', 'additionalAuthors.fileID')
            ->leftJoin('filebase1_file_content as content', function ($join): void {
                $join->on('file.fileID', '=', 'content.fileID')
                    ->whereNull('content.languageID'); // We don't do that here
            })
            ->leftJoin('filebase1_file_option_value as optionSourceCode', function ($join): void {
                $join->on('file.fileID', '=', 'optionSourceCode.fileID')
                    ->whereIn('optionSourceCode.optionID', [1, 5]); // Two different options for source code? Sure.
            })
            ->leftJoin('filebase1_file_option_value as optionContainsAI', function ($join): void {
                $join->on('file.fileID', '=', 'optionContainsAI.fileID')
                    ->where('optionContainsAI.optionID', 7); // AI option
            })
            ->leftJoin('filebase1_file_option_value as optionContainsAds', function ($join): void {
                $join->on('file.fileID', '=', 'optionContainsAds.fileID')
                    ->where('optionContainsAds.optionID', 3); // Ad option
            })
            ->leftJoin('wcf1_label_object as label', function ($join): void {
                $join->on('file.fileID', '=', 'label.objectID')
                    ->where('label.objectTypeID', 387); // File object type
            })
            ->leftJoin('wcf1_label as spt_version', function ($join): void {
                $join->on('label.labelID', '=', 'spt_version.labelID')
                    ->where('spt_version.groupID', 1); // SPT Version group
            })
            ->groupBy('file.fileID')
            ->orderBy('file.fileID')
            ->having('spt_version_label', '!=', '')
            ->chunk(1000, function (Collection $records) use ($localLicenses): void {
                /** @var Collection<int, object> $records */

                /** @var Collection<int, HubMod> $hubMods */
                $hubMods = $records->map(fn (object $record): HubMod => HubMod::fromArray((array) $record));
                $hubOwnerIds = $hubMods->pluck('userID')->filter()->unique();

                /** @var EloquentCollection<int, User> $localOwners */
                $localOwners = User::query()->whereIn('hub_id', $hubOwnerIds)->get()->keyBy('hub_id');

                // Fetch additional author users for this batch
                $allAdditionalAuthorHubIds = $hubMods
                    ->pluck('additional_authors')
                    ->flatMap(fn ($ids) => empty($ids) ? [] : explode(',', $ids))
                    ->map(fn ($id): string => trim($id))
                    ->filter()
                    ->unique()
                    ->all();

                /** @var EloquentCollection<int, User> $localAuthors */
                $localAuthors = User::query()->whereIn('hub_id', $allAdditionalAuthorHubIds)->get()->keyBy('hub_id');

                $this->processModBatch(
                    $hubMods,
                    $localOwners,
                    $localLicenses,
                    $localAuthors
                );
            });
    }

    /**
     * Process a batch of Hub mods.
     *
     * @param  Collection<int, HubMod>  $hubMods
     * @param  EloquentCollection<int, User>  $localOwners
     * @param  EloquentCollection<int, License>  $localLicenses
     * @param  EloquentCollection<int, User>  $localAuthors
     */
    private function processModBatch(
        Collection $hubMods,
        EloquentCollection $localOwners,
        EloquentCollection $localLicenses,
        EloquentCollection $localAuthors
    ): void {
        // Filter out deleted mods and collect their hub_ids for deletion
        $deletedHubIds = $hubMods->filter(fn (HubMod $hubMod): bool => (bool) $hubMod->isDeleted)
            ->pluck('fileID')
            ->all();
        $activeHubMods = $hubMods->reject(fn (HubMod $hubMod): bool => (bool) $hubMod->isDeleted);

        // Delete mods from the database that are marked as deleted
        if (! empty($deletedHubIds)) {
            Mod::query()->whereIn('hub_id', $deletedHubIds)->delete();
        }

        // Prepare data for upsert for only active mods.
        $modData = $activeHubMods->map(function (HubMod $hubMod) use ($localLicenses, $localOwners): array {
            $thumbnailData = $this->fetchModThumbnail($hubMod);

            return [
                'hub_id' => $hubMod->fileID,
                'owner_id' => $localOwners->get($hubMod->userID)?->id,
                'license_id' => $localLicenses->get($hubMod->licenseID)?->id,
                'name' => $hubMod->subject,
                'slug' => Str::slug($hubMod->subject),
                'teaser' => $hubMod->getTeaser(),
                'description' => $hubMod->getCleanMessage(),
                'thumbnail' => $thumbnailData['path'],
                'thumbnail_hash' => $thumbnailData['hash'],
                'source_code_url' => $hubMod->getSourceCodeLink(),
                'featured' => (bool) $hubMod->isFeatured,
                'contains_ai_content' => (bool) $hubMod->contains_ai,
                'contains_ads' => (bool) $hubMod->contains_ads,
                'disabled' => (bool) $hubMod->isDisabled,
                'published_at' => $hubMod->getTime(),
                'created_at' => $hubMod->getTime(),
                'updated_at' => $hubMod->getLastChangeTime(),
            ];
        })->toArray();

        // Split upsert into separate insert/update operations to avoid ID gaps
        Mod::withoutEvents(function () use ($modData): void {
            // Get existing hub_ids to determine which records to update vs insert
            $hubIds = collect($modData)->pluck('hub_id');
            $existingMods = Mod::query()->whereIn('hub_id', $hubIds)->pluck('hub_id')->toArray();

            // Split data into inserts (new) and updates (existing)
            $insertData = [];
            $updateData = [];

            foreach ($modData as $mod) {
                if (in_array($mod['hub_id'], $existingMods)) {
                    $updateData[] = $mod;
                } else {
                    $insertData[] = $mod;
                }
            }

            // Insert new mods (only allocates IDs for actual new records)
            if (! empty($insertData)) {
                Mod::query()->insert($insertData);
            }

            // Update existing mods (no ID allocation)
            foreach ($updateData as $mod) {
                Mod::query()->where('hub_id', $mod['hub_id'])->update([
                    'owner_id' => $mod['owner_id'],
                    'license_id' => $mod['license_id'],
                    'name' => $mod['name'],
                    'slug' => $mod['slug'],
                    'teaser' => $mod['teaser'],
                    'description' => $mod['description'],
                    'thumbnail' => $mod['thumbnail'],
                    'thumbnail_hash' => $mod['thumbnail_hash'],
                    'source_code_url' => $mod['source_code_url'],
                    'featured' => $mod['featured'],
                    'contains_ai_content' => $mod['contains_ai_content'],
                    'contains_ads' => $mod['contains_ads'],
                    'disabled' => $mod['disabled'],
                    'published_at' => $mod['published_at'],
                    'created_at' => $mod['created_at'],
                    'updated_at' => $mod['updated_at'],
                ]);
            }
        });

        $hubModIds = $activeHubMods->pluck('fileID');

        /** @var EloquentCollection<int, Mod> $localMods */
        $localMods = Mod::query()->whereIn('hub_id', $hubModIds)->get()->keyBy('hub_id');

        // Prepare author relationships for syncing
        $authorSyncData = [];
        $activeHubMods->each(function (HubMod $hubMod) use ($localMods, $localAuthors, &$authorSyncData): void {
            if ($localMod = $localMods->get($hubMod->fileID)) {
                $additionalAuthorHubIds = $hubMod->additional_authors
                    ? collect(explode(',', $hubMod->additional_authors))
                        ->map(fn ($id): string => trim($id))
                        ->filter()
                        ->all()
                    : [];

                $authorIdsToSync = collect($additionalAuthorHubIds)
                    ->map(fn ($hubId) => $localAuthors->get((int) $hubId)?->id)
                    ->filter()
                    ->all();

                $authorSyncData[$localMod->id] = $authorIdsToSync;
            }
        });

        Mod::withoutEvents(function () use ($localMods, $authorSyncData): void {
            foreach ($authorSyncData as $modId => $authorIds) {
                if ($mod = $localMods->find($modId)) {
                    $mod->authors()->sync($authorIds);
                }
            }
        });
    }

    /**
     * Fetch the mod thumbnail from the Hub.
     *
     * @return array{path: string, hash: string}
     *
     * @throws ConnectionException
     */
    private function fetchModThumbnail(HubMod $hubMod): array
    {
        if (! empty($hubMod->getFontAwesomeIcon())) {
            try {
                $path = self::generateAwesomeFontThumbnail($hubMod->fileID, $hubMod->getFontAwesomeIcon());

                return ['path' => $path, 'hash' => ''];
            } catch (ImagickDrawException|ImagickException) {
                Log::error('There was an error attempting to generate the Font Awesome thumbnail for mod with hub ID: '.$hubMod->fileID);

                return ['path' => '', 'hash' => ''];
            }
        }

        // If any of the required fields are empty, return empty values.
        if (empty($hubMod->iconHash) || empty($hubMod->iconExtension)) {
            return ['path' => '', 'hash' => ''];
        }

        // Check if we need to update the thumbnail by comparing hashes
        $forceUpdate = false;
        $existingMod = Mod::query()->where('hub_id', $hubMod->fileID)->first();
        if ($existingMod && $existingMod->thumbnail_hash !== $hubMod->iconHash) {
            $forceUpdate = true;
        }

        $hashShort = substr($hubMod->iconHash, 0, 2);
        $fileName = $hubMod->fileID.'.'.$hubMod->iconExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/files/images/file/'.$hashShort.'/'.$fileName;
        $relativePath = 'mods/'.$fileName;

        $path = $this->fetchAndStoreImage($hubUrl, $relativePath, $forceUpdate);

        return ['path' => $path, 'hash' => $hubMod->iconHash];
    }

    /**
     * Generate a thumbnail from a Font Awesome icon.
     *
     * @throws ImagickException|ImagickDrawException
     */
    private function generateAwesomeFontThumbnail(int $fileId, string $fontAwesomeIcon): string
    {
        // Determine the storage disk based on the application environment
        $disk = match (config('app.env')) {
            'production' => 'r2',  // Cloudflare R2 Storage
            default => 'public',  // Local storage
        };

        $relativePath = 'mods/'.$fileId.'.png';

        // If the image already exists, return its path
        if (Storage::disk($disk)->exists($relativePath)) {
            return $relativePath;
        }

        $width = 512;
        $height = 512;
        $fontSize = 250;

        // Create a new image with a black background
        $image = new Imagick;
        $backgroundColor = new ImagickPixel('black');
        $image->newImage($width, $height, $backgroundColor);
        $image->setImageFormat('png');

        // Prepare the drawing object for the icon
        $draw = new ImagickDraw;
        $draw->setFillColor(new ImagickPixel('white'));

        // Set the Font Awesome path
        $fontPath = Storage::disk('local')->path('fonts/fontawesome-webfont.ttf');
        if (! file_exists($fontPath)) {
            Log::error('Font Awesome font file not found at: '.$fontPath);
            throw new ImagickException('Font file not found.');
        }

        $draw->setFont($fontPath);
        $draw->setFontSize($fontSize);

        // Calculate metrics for centering the icon on the image
        $metrics = $image->queryFontMetrics($draw, $fontAwesomeIcon);
        $textWidth = $metrics['textWidth'];
        $textHeight = $metrics['textHeight'];
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2 + $metrics['ascender'];

        // Draw the icon text onto the image
        $image->annotateImage($draw, $x, $y, 0, $fontAwesomeIcon);

        // Retrieve the image data as a binary string
        $imageData = $image->getImageBlob();

        // Store the image on the selected disk and return its relative path
        Storage::disk($disk)->put($relativePath, $imageData);
        unset($image, $imageData);

        return $relativePath;
    }

    /**
     * Get all mod versions from the Hub database and pass them in batches to be processed.
     **/
    private function getHubModVersions(): void
    {
        $this->sptVersionConstraints = [];

        DB::connection('hub')
            ->table('filebase1_file_version as version')
            ->select(
                'version.*',
                DB::raw("IFNULL(ANY_VALUE(spt_version.label), '') AS spt_version_tag"),
                DB::raw("IFNULL(ANY_VALUE(version_content.description), '') AS description"),
                DB::raw("IFNULL(GROUP_CONCAT(TRIM(option_values.optionValue) ORDER BY option_values.optionValue SEPARATOR ','), '') AS virus_total_links")
            )
            ->leftJoin('filebase1_file_version_content as version_content', 'version.versionID', '=', 'version_content.versionID')
            ->leftJoin('filebase1_file_option_value as option_values', function ($join): void {
                $join->on('version.fileID', '=', 'option_values.fileID')
                    ->whereIn('option_values.optionID', [6, 2]); // VirusTotal links
            })
            ->leftJoin('wcf1_label_object as label', 'version.fileID', '=', 'label.objectID')
            ->leftJoin('wcf1_label as spt_version', 'label.labelID', '=', 'spt_version.labelID')
            ->groupBy('version.versionID')
            ->orderBy('version.versionID', 'desc')
            ->chunk(2000, function (Collection $records): void {
                /** @var Collection<int, object> $records */

                /** @var Collection<int, HubModVersion> $hubModVersions */
                $hubModVersions = $records->map(fn (object $record): HubModVersion => HubModVersion::fromArray((array) $record));
                $hubModIds = $hubModVersions->pluck('fileID')->unique();

                /** @var EloquentCollection<int, Mod> $localMods */
                $localMods = Mod::query()->whereIn('hub_id', $hubModIds)->get()->keyBy('hub_id');

                $this->processModVersionBatch($hubModVersions, $localMods);
            });

        $this->processModVersionSptConstraints();
    }

    /**
     * Process a batch of Hub mod versions.
     *
     * @param  Collection<int, HubModVersion>  $hubModVersions
     * @param  EloquentCollection<int, Mod>  $localMods
     */
    private function processModVersionBatch(Collection $hubModVersions, EloquentCollection $localMods): void
    {
        // Prepare data for upsert.
        $modVersionData = [];

        $hubModVersions->each(function (HubModVersion $hubModVersion) use ($localMods, &$modVersionData): void {
            try {
                $version = Version::cleanModImport($hubModVersion->versionNumber);
                $localMod = $localMods->get($hubModVersion->fileID);

                // If mod doesn't exist locally, skip this version.
                if (! $localMod) {
                    return;
                }

                $modId = $localMod->id;

                // Accumulate the SPT version constraints for separate processing. Store with local mod ID as the key.
                $sptConstraint = $hubModVersion->getSptVersionConstraint();
                $this->sptVersionConstraints[$modId][$hubModVersion->versionID] = $sptConstraint;

                $publishedAt = $hubModVersion->getPublishedAt();
                $createdAt = Carbon::parse($hubModVersion->uploadTime, 'UTC')->toDateTimeString();

                $modVersionData[] = [
                    'hub_id' => $hubModVersion->versionID,
                    'mod_id' => $modId,
                    'version' => $version->getVersion(),
                    'version_major' => $version->getMajor(),
                    'version_minor' => $version->getMinor(),
                    'version_patch' => $version->getPatch(),
                    'version_labels' => $version->getLabels(),
                    'description' => $hubModVersion->getCleanDescription(),
                    'link' => $hubModVersion->downloadURL,
                    'virus_total_link' => $hubModVersion->getVirusTotalLink(),
                    'downloads' => max($hubModVersion->downloads, 0), // At least 0.
                    'disabled' => (bool) $hubModVersion->isDisabled,
                    'published_at' => $publishedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            } catch (InvalidVersionNumberException $e) {
                Log::warning(sprintf('Skipping Hub mod version ID %d for Hub mod %d due to version parsing error: %s', $hubModVersion->versionID, $hubModVersion->fileID, $e->getMessage()));
            } catch (Exception $e) {
                Log::error(sprintf('Error processing Hub mod version ID %d: %s', $hubModVersion->versionID, $e->getMessage()));
            }
        });

        // Split upsert into separate insert/update operations to avoid ID gaps
        if (! empty($modVersionData)) {
            ModVersion::withoutEvents(function () use ($modVersionData): void {
                // Get existing hub_ids to determine which records to update vs insert
                $hubIds = collect($modVersionData)->pluck('hub_id');
                $existingModVersions = ModVersion::query()->whereIn('hub_id', $hubIds)->pluck('hub_id')->toArray();

                // Split data into inserts (new) and updates (existing)
                $insertData = [];
                $updateData = [];

                foreach ($modVersionData as $modVersion) {
                    if (in_array($modVersion['hub_id'], $existingModVersions)) {
                        $updateData[] = $modVersion;
                    } else {
                        $insertData[] = $modVersion;
                    }
                }

                // Insert new mod versions (only allocates IDs for actual new records)
                if (! empty($insertData)) {
                    ModVersion::query()->insert($insertData);
                }

                // Update existing mod versions (no ID allocation)
                foreach ($updateData as $modVersion) {
                    ModVersion::query()->where('hub_id', $modVersion['hub_id'])->update([
                        'mod_id' => $modVersion['mod_id'],
                        'version' => $modVersion['version'],
                        'version_major' => $modVersion['version_major'],
                        'version_minor' => $modVersion['version_minor'],
                        'version_patch' => $modVersion['version_patch'],
                        'version_labels' => $modVersion['version_labels'],
                        'description' => $modVersion['description'],
                        'link' => $modVersion['link'],
                        'virus_total_link' => $modVersion['virus_total_link'],
                        'downloads' => $modVersion['downloads'],
                        'disabled' => $modVersion['disabled'],
                        'published_at' => $modVersion['published_at'],
                        'created_at' => $modVersion['created_at'],
                        'updated_at' => $modVersion['updated_at'],
                    ]);
                }
            });
        }
    }

    /**
     * Update the latest versions of mods with their SPT version constraints.
     */
    private function processModVersionSptConstraints(): void
    {
        if (empty($this->sptVersionConstraints)) {
            return;
        }

        $modIdsToProcess = array_keys($this->sptVersionConstraints);

        /** @var EloquentCollection<int, ModVersion> $allRelevantVersions */
        $allRelevantVersions = ModVersion::query()
            ->whereIn('mod_id', $modIdsToProcess)
            ->orderByDesc('version_major')
            ->orderByDesc('version_minor')
            ->orderByDesc('version_patch')
            ->orderBy('version_labels')
            ->get();

        if ($allRelevantVersions->isEmpty()) {
            return;
        }

        $versionsGroupedByMod = $allRelevantVersions->groupBy('mod_id');
        $now = Carbon::now('UTC')->toDateTimeString();

        ModVersion::withoutEvents(function () use ($versionsGroupedByMod, $now): void {
            foreach ($versionsGroupedByMod as $modId => $modVersions) {
                $latestModVersion = $modVersions->first();
                if (! $latestModVersion) {
                    continue;
                }

                $versionConstraintsForMod = $this->sptVersionConstraints[$modId] ?? null;
                $latestVersionHubId = $latestModVersion->hub_id;

                if ($versionConstraintsForMod && isset($versionConstraintsForMod[$latestVersionHubId])) {
                    $constraintValue = $versionConstraintsForMod[$latestVersionHubId];

                    if ($latestModVersion->spt_version_constraint !== $constraintValue) {
                        ModVersion::query()
                            ->where('id', $latestModVersion->id)
                            ->update([
                                'spt_version_constraint' => $constraintValue,
                                'updated_at' => $now,
                            ]);
                    }
                }
            }
        });
    }

    /**
     * Remove mods that have been deleted from the Hub.
     */
    private function removeDeletedHubMods(): void
    {
        $hubModIds = DB::connection('hub')
            ->table('filebase1_file')
            ->pluck('fileID')
            ->toArray();

        if (empty($hubModIds)) {
            return;
        }

        Mod::query()->whereNotIn('hub_id', $hubModIds)->delete();
    }

    /**
     * Remove mods that do not have a hub id. This ensures that anything uploaded directly to the Forge
     * is removed as "testing" data.
     */
    private function removeModsWithoutHubVersions(): void
    {
        Mod::query()->whereNull('hub_id')->delete();
    }
}
