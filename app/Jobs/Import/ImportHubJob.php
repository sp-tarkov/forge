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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
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

        Bus::chain([
            new ResolveSptVersionsJob,
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
        DB::connection('hub')
            ->table('wcf1_user as u')
            ->select('u.*', 'r.rankTitle')
            ->leftJoin('wcf1_user_rank as r', 'u.rankID', '=', 'r.rankID')
            ->orderBy('u.userID')
            ->chunk(7500, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubUser> $hubUsers */
                $hubUsers = $records->map(fn (object $record): HubUser => HubUser::fromArray((array) $record));

                $this->processUserBatch($hubUsers);
                $this->processUserBatchBans($hubUsers);
                $this->processUserBatchRoles($hubUsers);
            });
    }

    /**
     * Process a batch of Hub users.
     *
     * @param  Collection<int, HubUser>  $hubUsers
     */
    private function processUserBatch(Collection $hubUsers): void
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

        // Upsert batch of users based on their hub_id.
        User::withoutEvents(function () use ($userData): void {
            User::query()->upsert($userData, ['hub_id'], ['name', 'email', 'password', 'created_at', 'updated_at']);
        });
    }

    /**
     * Process a batch of Hub users and ban them if necessary.
     *
     * @param  Collection<int, HubUser>  $hubUsers
     */
    private function processUserBatchBans(Collection $hubUsers): void
    {
        $hubUsers->each(function (HubUser $hubUser): void {
            if ($hubUser->banned > 0) {
                if ($user = User::whereHubId($hubUser->userID)->first()) {
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
     */
    private function processUserBatchRoles(Collection $hubUsers): void
    {
        $hubUsers->each(function (HubUser $hubUser): void {
            if ($hubUser->banned) {
                return;
            }

            $rankTitle = $hubUser->getRankTitle();

            if (! empty($hubUser->rankID) && $rankTitle) {
                // Create the role if it doesn't exist by name.
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

                // Attach the role to the user.
                $user = User::whereHubId($hubUser->userID)->first();
                $user->assignRole($role);
            }
        });
    }

    /**
     * Get all user options from the Hub database and pass them in batches to be processed.
     */
    private function getHubUserOptions(): void
    {
        // Currently we're only getting rows where the about text has value.
        DB::connection('hub')
            ->table('wcf1_user_option_value')
            ->whereNotNull('userOption1') // About field
            ->whereNot('userOption1', value: '')
            ->orderBy('userID')
            ->chunk(5000, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubUserOptionValue> $hubUserOptionValues */
                $hubUserOptionValues = $records->map(fn (object $record): HubUserOptionValue => HubUserOptionValue::fromArray((array) $record));

                $this->processUserOptionValueBatch($hubUserOptionValues);
            });
    }

    /**
     * Process a batch of Hub user option values.
     *
     * @param  Collection<int, HubUserOptionValue>  $hubUserOptionValues
     */
    private function processUserOptionValueBatch(Collection $hubUserOptionValues): void
    {
        $now = Carbon::now('UTC')->toDateTimeString();

        $hubUserOptionValues
            ->filter(fn (HubUserOptionValue $hubUserOptionValue): bool => $hubUserOptionValue->getAbout() !== '')
            ->each(function (HubUserOptionValue $hubUserOptionValue) use ($now): void {
                User::withoutEvents(function () use ($hubUserOptionValue, $now): void {
                    User::query()->where('hub_id', $hubUserOptionValue->userID)
                        ->update([
                            'about' => $hubUserOptionValue->getAbout(),
                            'updated_at' => $now,
                        ]);
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

                $this->processUserAvatarBatch($hubUserAvatars);
            });
    }

    /**
     * Process a batch of Hub user avatars.
     *
     * @param  Collection<int, HubUserAvatar>  $hubUserAvatars
     *
     * @throws ConnectionException
     */
    private function processUserAvatarBatch(Collection $hubUserAvatars): void
    {
        $hubUserAvatars->each(function (HubUserAvatar $hubUserAvatar): void {
            $relativePath = $this->processUserAvatarImage($hubUserAvatar);

            if (! empty($relativePath)) {
                User::withoutEvents(function () use ($hubUserAvatar, $relativePath): void {
                    User::query()->where('hub_id', $hubUserAvatar->userID)
                        ->update([
                            'profile_photo_path' => $relativePath,
                            'updated_at' => Carbon::now('UTC')->toDateTimeString(),
                        ]);
                });
            }
        });
    }

    /**
     * Process (download) a user avatar image.
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

                $this->processUserCoverPhotoBatch($hubUsers);
            });
    }

    /**
     * Process a batch of cover photos for Hub users.
     *
     * @param  Collection<int, HubUser>  $hubUsers
     *
     * @throws ConnectionException
     */
    private function processUserCoverPhotoBatch(Collection $hubUsers): void
    {
        $hubUsers->each(function (HubUser $hubUser): void {
            $coverPhotoPath = $this->fetchUserCoverPhoto($hubUser);

            if (! empty($coverPhotoPath)) {
                User::withoutEvents(function () use ($hubUser, $coverPhotoPath): void {
                    User::query()->where('hub_id', $hubUser->userID)
                        ->update([
                            'cover_photo_path' => $coverPhotoPath,
                            'updated_at' => Carbon::now('UTC')->toDateTimeString(),
                        ]);
                });
            }
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
     * Process (download) and store an image from the given URL.
     *
     * @throws ConnectionException
     */
    private function fetchAndStoreImage(string $hubUrl, string $relativePath): string
    {
        // Determine the disk to use based on the environment.
        $disk = match (config('app.env')) {
            'production' => 'r2', // Cloudflare R2 Storage
            default => 'public',  // Local storage
        };

        // If the image already exists, return its path.
        if (Storage::disk($disk)->exists($relativePath)) {
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

        DB::connection('hub')
            ->table('wcf1_user_follow')
            ->orderBy('followID')
            ->chunk(5000, function (Collection $records) use (&$followsGroupedByFollower): void {
                /** @var Collection<int, object> $records */
                $records
                    ->map(fn (object $record): HubUserFollow => HubUserFollow::fromArray((array) $record))
                    ->each(function (HubUserFollow $hubUserFollow) use (&$followsGroupedByFollower): void {
                        $followerId = User::whereHubId($hubUserFollow->userID)->value('id');
                        $followingId = User::whereHubId($hubUserFollow->followUserID)->value('id');

                        if (! $followerId || ! $followingId) {
                            return;
                        }

                        $followsGroupedByFollower[$followerId][$followingId] = [
                            'created_at' => Carbon::parse($hubUserFollow->time, 'UTC'),
                            'updated_at' => Carbon::parse($hubUserFollow->time, 'UTC'),
                        ];
                    });
            });

        foreach ($followsGroupedByFollower as $followerId => $followings) {
            if ($user = User::query()->find($followerId)) {
                $user->following()->sync($followings);
            }
        }
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
        // Prepare data for upsert.
        $licenseData = $hubModLicenses->map(fn (HubModLicense $hubModLicense): array => [
            'hub_id' => $hubModLicense->licenseID,
            'name' => $hubModLicense->licenseName,
            'link' => $hubModLicense->licenseURL,
        ])->toArray();

        // Upsert batch of users based on their hub_id.
        License::withoutEvents(function () use ($licenseData): void {
            License::query()->upsert($licenseData, ['hub_id'], ['name', 'link']);
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
                $release->tag_name = Version::cleanSptImport($release->tag_name)->getVersion();

                return $release;
            })
            ->filter(fn (GitHubSptVersion $release): bool => $release->tag_name !== '');

        $this->processSptVersions($gitHubSptReleases);
    }

    /**
     * Process the SPT versions, inserting them into the database.
     *
     * @param  Collection<int, GitHubSptVersion>  $releases
     *
     * @throws InvalidVersionNumberException
     */
    private function processSptVersions(Collection $releases): void
    {
        // Sort the releases by the tag_name using Semver::sort
        $sortedVersions = Semver::sort($releases->pluck('tag_name')->toArray());
        $latestVersion = end($sortedVersions);

        // Ensure a "dummy" version exists so we can resolve outdated mods to it.
        $versionData[] = [
            'version' => '0.0.0',
            'version_major' => 0,
            'version_minor' => 0,
            'version_patch' => 0,
            'version_labels' => '',
            'link' => '',
            'color_class' => 'black',
            'created_at' => Carbon::now('UTC')->toDateTimeString(),
            'updated_at' => Carbon::now('UTC')->toDateTimeString(),
        ];

        $releases->each(function (GitHubSptVersion $release) use ($latestVersion, &$versionData): void {
            $version = new Version($release->tag_name);
            $versionData[] = [
                'version' => $version->getVersion(),
                'version_major' => $version->getMajor(),
                'version_minor' => $version->getMinor(),
                'version_patch' => $version->getPatch(),
                'version_labels' => $version->getLabels(),
                'link' => $release->html_url,
                'color_class' => self::detectSptVersionColor($release->tag_name, $latestVersion),
                'created_at' => Carbon::parse($release->published_at, 'UTC')->toDateTimeString(),
                'updated_at' => Carbon::parse($release->published_at, 'UTC')->toDateTimeString(),
            ];
        });

        // Upsert SPT Versions based on their version string.
        SptVersion::withoutEvents(function () use ($versionData): void {
            SptVersion::query()->upsert($versionData, ['version'], [
                'version_major', 'version_minor', 'version_patch', 'version_labels', 'link', 'color_class',
                'created_at', 'updated_at',
            ]);
        });
    }

    /**
     * Determine the color for the SPT version.
     *
     * @throws InvalidVersionNumberException
     */
    private static function detectSptVersionColor(string $rawVersion, false|string $rawLatestVersion): string
    {
        if ($rawVersion === '0.0.0') {
            return 'gray';
        }

        $version = new Version($rawVersion);
        $currentMajor = $version->getMajor();
        $currentMinor = $version->getMinor();

        $latestVersion = new Version($rawLatestVersion);
        $latestMajor = $latestVersion->getMajor();
        $latestMinor = $latestVersion->getMinor();

        if ($currentMajor !== $latestMajor) {
            return 'gray';
        }

        $minorDifference = $latestMinor - $currentMinor;

        return match ($minorDifference) {
            0 => 'green',
            1 => 'lime',
            2 => 'yellow',
            3 => 'red',
            default => 'gray',
        };
    }

    /**
     * Get all mods from the Hub database and pass them in batches to be processed.
     **/
    private function getHubMods(): void
    {
        DB::connection('hub')
            ->table('filebase1_file as file')
            ->select(
                DB::raw('file.fileID as fileIDChunkKey'),
                'file.*',
                DB::raw('ANY_VALUE(content.subject) AS subject'),
                DB::raw('ANY_VALUE(content.teaser) AS teaser'),
                DB::raw('ANY_VALUE(content.message) AS message'),
                DB::raw("IFNULL(GROUP_CONCAT(TRIM(additionalAuthors.userID) ORDER BY additionalAuthors.userID SEPARATOR ','), '') AS additional_authors"),
                DB::raw("IFNULL(GROUP_CONCAT(TRIM(optionSourceCode.optionValue) ORDER BY optionSourceCode.optionValue SEPARATOR ','), '') AS source_code_link"),
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
                    ->where('optionContainsAds.optionID', 3); // Ads option
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
            ->chunk(1000, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubMod> $hubMods */
                $hubMods = $records->map(fn (object $record): HubMod => HubMod::fromArray((array) $record));

                $this->processModBatch($hubMods);
            });
    }

    /**
     * Process a batch of Hub mods.
     *
     * @param  Collection<int, HubMod>  $hubMods
     */
    private function processModBatch(Collection $hubMods): void
    {
        // Prepare data for upsert.
        $modData = $hubMods->map(fn (HubMod $hubMod): array => [
            'hub_id' => $hubMod->fileID,
            'name' => $hubMod->subject,
            'slug' => Str::slug($hubMod->subject),
            'teaser' => $hubMod->getTeaser(),
            'description' => $hubMod->getCleanMessage(),
            'thumbnail' => $this->fetchModThumbnail($hubMod),
            'license_id' => $hubMod->getLicenseId(),
            'source_code_link' => $hubMod->getSourceCodeLink(),
            'featured' => (bool) $hubMod->isFeatured,
            'contains_ai_content' => (bool) $hubMod->contains_ai,
            'contains_ads' => (bool) $hubMod->contains_ads,
            'disabled' => (bool) $hubMod->isDisabled,
            'published_at' => $hubMod->getTime(),
            'created_at' => $hubMod->getTime(),
            'updated_at' => $hubMod->getLastChangeTime(),
        ])->toArray();

        // Upsert batch of mods based on their hub_id.
        Mod::withoutEvents(function () use ($modData): void {
            Mod::query()->upsert($modData, ['hub_id'], [
                'name', 'slug', 'teaser', 'description', 'thumbnail', 'license_id', 'source_code_link', 'featured',
                'contains_ai_content', 'contains_ads', 'disabled', 'published_at', 'created_at', 'updated_at',
            ]);
        });

        // Attach the authors to the mods.
        $hubMods->each(function (HubMod $hubMod): void {
            $authors = $hubMod->getAllAuthors();

            Mod::withoutEvents(function () use ($hubMod, $authors): void {
                Mod::query()->whereHubId($hubMod->fileID)->first()->users()->sync($authors);
            });
        });
    }

    /**
     * Fetch the mod thumbnail from the Hub.
     *
     * @throws ConnectionException
     */
    private function fetchModThumbnail(HubMod $hubMod): string
    {
        if (! empty($hubMod->getFontAwesomeIcon())) {
            try {
                return self::generateAwesomeFontThumbnail($hubMod->fileID, $hubMod->getFontAwesomeIcon());
            } catch (ImagickDrawException|ImagickException) {
                Log::error('There was an error attempting to generate the Font Awesome thumbnail for mod with hub ID: '.$hubMod->fileID);

                return '';
            }
        }

        // If any of the required fields are empty, return an empty string.
        if (empty($hubMod->iconHash) || empty($hubMod->iconExtension)) {
            return '';
        }

        $hashShort = substr($hubMod->iconHash, 0, 2);
        $fileName = $hubMod->fileID.'.'.$hubMod->iconExtension;
        $hubUrl = 'https://hub.sp-tarkov.com/files/images/file/'.$hashShort.'/'.$fileName;
        $relativePath = 'mods/'.$fileName;

        return $this->fetchAndStoreImage($hubUrl, $relativePath);
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
                    ->whereIn('option_values.optionID', [6, 2]);
            })
            ->leftJoin('wcf1_label_object as label', 'version.fileID', '=', 'label.objectID')
            ->leftJoin('wcf1_label as spt_version', 'label.labelID', '=', 'spt_version.labelID')
            ->groupBy('version.versionID')
            ->orderBy('version.versionID', 'desc')
            ->chunk(2000, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, HubModVersion> $hubModVersion */
                $hubModVersion = $records->map(fn (object $record): HubModVersion => HubModVersion::fromArray((array) $record));

                $this->processModVersionBatch($hubModVersion);
            });

        $this->processModVersionSptConstraints();
    }

    /**
     * Process a batch of Hub mod versions.
     *
     * @param  Collection<int, HubModVersion>  $hubModVersions
     */
    private function processModVersionBatch(Collection $hubModVersions): void
    {
        // Prepare data for upsert.
        $modVersionData = $hubModVersions
            ->map(function (HubModVersion $hubModVersion): array {
                $version = Version::cleanModImport($hubModVersion->versionNumber);

                // Find the mod ID based on the version's hub_id.
                $modId = Mod::whereHubId($hubModVersion->fileID)->value('id');

                // Accumulate the SPT version constraints for separate processing.
                $this->sptVersionConstraints[$modId][$hubModVersion->versionID] = $hubModVersion->getSptVersionConstraint();

                return [
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
                    'published_at' => $hubModVersion->getPublishedAt(),
                    'created_at' => Carbon::parse($hubModVersion->uploadTime, 'UTC')->toDateTimeString(),
                    'updated_at' => Carbon::parse($hubModVersion->uploadTime, 'UTC')->toDateTimeString(),
                ];
            })
            ->filter(fn (array $hubModVersion): bool => $hubModVersion['mod_id'] !== null)
            ->toArray();

        // Upsert batch of mods based on their hub_id.
        ModVersion::withoutEvents(function () use ($modVersionData): void {
            ModVersion::query()->upsert($modVersionData, ['hub_id'], [
                'mod_id', 'version', 'version_major', 'version_minor', 'version_patch', 'version_labels', 'description',
                'link', 'virus_total_link', 'downloads', 'disabled', 'published_at', 'created_at', 'updated_at',
            ]);
        });
    }

    /**
     * Update the latest versions of mods with their SPT version constraints.
     */
    private function processModVersionSptConstraints(): void
    {
        foreach ($this->sptVersionConstraints as $modId => $versionConstraints) {
            $latestModVersion = ModVersion::query()->where('mod_id', $modId)
                ->orderByDesc('version_major')
                ->orderByDesc('version_minor')
                ->orderByDesc('version_patch')
                ->orderBy('version_labels')
                ->first();

            if (! $latestModVersion) {
                continue;
            }

            // Check if the latest version's hub_id is in the accumulated constraints.
            $versionId = $latestModVersion->hub_id;
            if (isset($versionConstraints[$versionId])) {
                // Update only the latest version's constraint.
                $latestModVersion->updateQuietly(['spt_version_constraint' => $versionConstraints[$versionId]]);
            }
        }
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
}
