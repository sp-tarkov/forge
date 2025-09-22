<?php

declare(strict_types=1);

namespace App\Jobs\Import;

use App\Exceptions\InvalidVersionNumberException;
use App\Jobs\Import\DataTransferObjects\CommentDto;
use App\Jobs\Import\DataTransferObjects\CommentLikeDto;
use App\Jobs\Import\DataTransferObjects\CommentResponseDto;
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
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModSourceCodeLink;
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
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\UniqueConstraintViolationException;
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
use Illuminate\Support\Str;

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
        $this->getHubComments();
        $this->removeDeletedHubMods();
        $this->removeModsWithoutHubVersions();

        Bus::chain([
            (new ResolveSptVersionsJob)->onQueue('long'),
            new ResolveDependenciesJob,
            new SptVersionModCountsJob,
            new UpdateModDownloadsJob,
            (new SearchSyncJob)->onQueue('long'),
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
            ->chunkById(4000, function (Collection $records) use ($roles): void {
                /** @var Collection<int, object> $records */

                /** @var Collection<int, HubUser> $hubUsers */
                $hubUsers = $records->map(fn (object $record): HubUser => HubUser::fromArray((array) $record));

                $localUsers = $this->processUserBatch($hubUsers);
                $this->processUserBatchBans($hubUsers, $localUsers);
                $this->processUserBatchRoles($hubUsers, $localUsers, $roles);
            }, 'u.userID');
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

        // Check for duplicate hub_ids or emails within the batch
        $hubIdCounts = collect($userData)->countBy('hub_id');
        if ($hubIdCounts->filter(fn (int $count): bool => $count > 1)->isNotEmpty()) {
            Log::warning('Duplicate hub_ids found within batch', [
                'duplicates' => $hubIdCounts->filter(fn (int $count): bool => $count > 1)->toArray(),
            ]);
        }

        $emailCounts = collect($userData)->countBy('email');
        if ($emailCounts->filter(fn (int $count): bool => $count > 1)->isNotEmpty()) {
            Log::warning('Duplicate emails found within batch', [
                'duplicates' => $emailCounts->filter(fn (int $count): bool => $count > 1)->toArray(),
            ]);
        }

        // Remove duplicates within the batch, keeping only the first occurrence
        $userData = collect($userData)
            ->unique('hub_id')
            ->unique('email')
            ->values()
            ->toArray();

        // Get existing hub_ids and emails to determine which records to update vs. insert
        // Do this OUTSIDE withoutEvents to ensure we see committed data
        $hubIds = collect($userData)->pluck('hub_id');
        $emails = collect($userData)->pluck('email');

        // Get all fresh existing users by hub_id and email
        $existingUsersByHubId = User::query()
            ->whereIn('hub_id', $hubIds)
            ->get()
            ->keyBy('hub_id');
        $existingUsersByEmail = User::query()
            ->whereIn('email', $emails)
            ->get()
            ->keyBy('email');

        // Split upsert into separate insert/update operations to avoid ID gaps
        User::withoutEvents(function () use ($userData, $existingUsersByHubId, $existingUsersByEmail): void {

            $insertData = [];
            $updateData = [];

            foreach ($userData as $user) {
                $existingByHubId = $existingUsersByHubId->get($user['hub_id']);
                $existingByEmail = $existingUsersByEmail->get($user['email']);

                // If user exists by hub_id, update them
                if ($existingByHubId) {
                    // Check if the email is being changed to one that belongs to a different user
                    // Log the conflict and skip this user to avoid constraint violation
                    if ($existingByEmail && $existingByEmail->hub_id !== $user['hub_id']) {
                        Log::warning('Email conflict during hub import', [
                            'hub_id' => $user['hub_id'],
                            'attempted_email' => $user['email'],
                            'conflicting_user_hub_id' => $existingByEmail->hub_id,
                        ]);

                        continue;
                    }

                    $updateData[] = $user;

                } elseif ($existingByEmail) {
                    // Email exists but hub_id doesn't
                    if ($existingByEmail->hub_id === null) {
                        // User exists with this email but no hub_id - update them with the hub_id
                        Log::info('Updating existing user with hub_id', [
                            'user_id' => $existingByEmail->id,
                            'email' => $user['email'],
                            'new_hub_id' => $user['hub_id'],
                        ]);

                        // Update the user with hub_id and other hub data
                        $updateData[] = $user;
                    } else {
                        // Email exists with a different hub_id - this is a conflict, log and skip
                        Log::warning('New hub user has email that already exists', [
                            'hub_id' => $user['hub_id'],
                            'email' => $user['email'],
                            'existing_user_hub_id' => $existingByEmail->hub_id,
                        ]);

                        continue;
                    }
                } else {
                    $insertData[] = $user; // New user
                }
            }

            if (! empty($insertData)) {
                try {
                    User::query()->insert($insertData);
                } catch (UniqueConstraintViolationException $e) {
                    // If batch insert fails due to duplicate, try inserting individually
                    Log::warning('Batch insert failed due to duplicate, attempting individual inserts', [
                        'error' => $e->getMessage(),
                        'batch_size' => count($insertData),
                    ]);

                    foreach ($insertData as $userData) {
                        try {
                            User::query()->insert([$userData]);
                        } catch (UniqueConstraintViolationException $individualException) {
                            // Check which constraint was violated
                            $message = $individualException->getMessage();
                            if (str_contains($message, 'users_hub_id_unique')) {
                                Log::warning('Duplicate hub_id during individual insert', [
                                    'hub_id' => $userData['hub_id'],
                                    'email' => $userData['email'],
                                ]);
                            } elseif (str_contains($message, 'users_email_unique')) {
                                Log::warning('Duplicate email during individual insert', [
                                    'hub_id' => $userData['hub_id'],
                                    'email' => $userData['email'],
                                ]);
                            } else {
                                Log::warning('Unknown unique constraint violation during individual insert', [
                                    'hub_id' => $userData['hub_id'],
                                    'email' => $userData['email'],
                                    'error' => $message,
                                ]);
                            }
                        }
                    }
                }
            }

            // Update existing users
            foreach ($updateData as $user) {
                // Try to update by hub_id first, if no rows affected, update by email
                $affected = User::query()->where('hub_id', $user['hub_id'])->update([
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => $user['password'],
                    'created_at' => $user['created_at'],
                    'updated_at' => $user['updated_at'],
                ]);

                // If no rows were updated by hub_id, try updating by email (for users with null hub_id)
                if ($affected === 0) {
                    User::query()->where('email', $user['email'])->whereNull('hub_id')->update([
                        'hub_id' => $user['hub_id'],
                        'name' => $user['name'],
                        'password' => $user['password'],
                        'created_at' => $user['created_at'],
                        'updated_at' => $user['updated_at'],
                    ]);
                }
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
        // Get rows where either the about text or timezone has value.
        DB::connection('hub')
            ->table('wcf1_user_option_value')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $q): void {
                    $q->whereNotNull('userOption1') // About field
                        ->whereNot('userOption1', value: '');
                })
                    ->orWhere(function (Builder $q): void {
                        $q->whereNotNull('userOption14') // Timezone field
                            ->whereNot('userOption14', value: '');
                    });
            })
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
                ->filter(fn (HubUserOptionValue $hubUserOptionValue): bool => $hubUserOptionValue->getAbout() !== '' || $hubUserOptionValue->getTimezone() !== null)
                ->each(function (HubUserOptionValue $hubUserOptionValue) use ($localUsers, $now): void {
                    if ($localUser = $localUsers->get($hubUserOptionValue->userID)) {
                        $updateData = ['updated_at' => $now];

                        // Update about text if present
                        if ($hubUserOptionValue->getAbout() !== '') {
                            $updateData['about'] = $hubUserOptionValue->getAbout();
                        }

                        // Update timezone if present and not already set locally
                        $timezone = $hubUserOptionValue->getTimezone();
                        if ($timezone !== null && empty($localUser->timezone)) {
                            $updateData['timezone'] = $timezone;
                        }

                        User::query()
                            ->where('id', $localUser->id)
                            ->update($updateData);
                    }
                });
        });
    }

    /**
     * Get all user avatars from the Hub database and pass them in batches to be processed.
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
     */
    private function processUserAvatarBatch(Collection $hubUserAvatars, EloquentCollection $localUsers): void
    {
        $hubUserAvatars->each(function (HubUserAvatar $hubUserAvatar) use ($localUsers): void {
            if ($hubUserAvatar->userID && ($localUser = $localUsers->get($hubUserAvatar->userID))) {
                ImportHubImageJob::dispatch(
                    'user_avatar',
                    $localUser->id,
                    $hubUserAvatar->toArray()
                )->onQueue('default');
            }
        });
    }

    /**
     * Get all user cover photos from the Hub database and pass them in batches to be processed.
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
     */
    private function processUserCoverPhotoBatch(Collection $hubUsers, EloquentCollection $localUsers): void
    {
        $hubUsers->each(function (HubUser $hubUser) use ($localUsers): void {
            if ($localUser = $localUsers->get($hubUser->userID)) {
                ImportHubImageJob::dispatch(
                    'user_cover',
                    $localUser->id,
                    $hubUser->toArray()
                )->onQueue('default');
            }
        });
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
            // Get existing hub_ids to determine which records to update vs. insert
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
            // Get existing versions to determine which records to update vs. insert
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
            ->leftJoin('filebase1_file_content as content', function (JoinClause $join): void {
                $join->on('file.fileID', '=', 'content.fileID')
                    ->whereNull('content.languageID'); // We don't do that here
            })
            ->leftJoin('filebase1_file_option_value as optionSourceCode', function (JoinClause $join): void {
                $join->on('file.fileID', '=', 'optionSourceCode.fileID')
                    ->whereIn('optionSourceCode.optionID', [1, 5]); // Two different options for source code? Sure.
            })
            ->leftJoin('filebase1_file_option_value as optionContainsAI', function (JoinClause $join): void {
                $join->on('file.fileID', '=', 'optionContainsAI.fileID')
                    ->where('optionContainsAI.optionID', 7); // AI option
            })
            ->leftJoin('filebase1_file_option_value as optionContainsAds', function (JoinClause $join): void {
                $join->on('file.fileID', '=', 'optionContainsAds.fileID')
                    ->where('optionContainsAds.optionID', 3); // Ad option
            })
            ->leftJoin('wcf1_label_object as label', function (JoinClause $join): void {
                $join->on('file.fileID', '=', 'label.objectID')
                    ->where('label.objectTypeID', 387); // File object type
            })
            ->leftJoin('wcf1_label as spt_version', function (JoinClause $join): void {
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
                    ->flatMap(fn (?string $ids): array => empty($ids) ? [] : explode(',', $ids))
                    ->map(fn (string $id): string => trim($id))
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
        $modData = $activeHubMods->map(fn (HubMod $hubMod): array => [
            'hub_id' => $hubMod->fileID,
            'owner_id' => $localOwners->get($hubMod->userID)?->id,
            'license_id' => $localLicenses->get($hubMod->licenseID)?->id,
            'name' => $hubMod->subject,
            'slug' => Str::slug($hubMod->subject),
            'teaser' => $hubMod->getTeaser(),
            'description' => $hubMod->getCleanMessage(),
            'thumbnail' => '', // Will be updated by ImportHubImageJob
            'thumbnail_hash' => '', // Will be updated by ImportHubImageJob
            'featured' => (bool) $hubMod->isFeatured,
            'contains_ai_content' => (bool) $hubMod->contains_ai,
            'contains_ads' => (bool) $hubMod->contains_ads,
            'disabled' => (bool) $hubMod->isDisabled,
            'comments_disabled' => ! (bool) $hubMod->enableComments,
            'published_at' => $hubMod->getTime(),
            'created_at' => $hubMod->getTime(),
            'updated_at' => $hubMod->getLastChangeTime(),
        ])->toArray();

        // Split upsert into separate insert/update operations to avoid ID gaps
        Mod::withoutEvents(function () use ($modData): void {
            // Get existing hub_ids to determine which records to update vs. insert
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
                    // Don't update thumbnail fields - they'll be handled by queued jobs
                    'featured' => $mod['featured'],
                    'contains_ai_content' => $mod['contains_ai_content'],
                    'contains_ads' => $mod['contains_ads'],
                    'disabled' => $mod['disabled'],
                    'comments_disabled' => $mod['comments_disabled'],
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
                        ->map(fn (string $id): string => trim($id))
                        ->filter()
                        ->all()
                    : [];

                $authorIdsToSync = collect($additionalAuthorHubIds)
                    ->map(fn (string $hubId): ?int => $localAuthors->get((int) $hubId)?->id)
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

        // Sync source code links
        $activeHubMods->each(function (HubMod $hubMod) use ($localMods): void {
            if ($localMod = $localMods->get($hubMod->fileID)) {
                // Get existing source code links for this mod
                $existingLinks = ModSourceCodeLink::query()->where('mod_id', $localMod->id)
                    ->pluck('url', 'id')
                    ->toArray();

                // Get new source code links from Hub
                $newLinks = $hubMod->getSourceCodeLinks();

                // Delete links that are no longer in the Hub data
                $linksToDelete = array_diff($existingLinks, $newLinks);
                if (! empty($linksToDelete)) {
                    ModSourceCodeLink::query()->whereIn('id', array_keys($linksToDelete))->delete();
                }

                // Add new links
                foreach ($newLinks as $url) {
                    // Check if this URL already exists for this mod
                    $existingLinkId = array_search($url, $existingLinks, true);

                    if ($existingLinkId === false) {
                        // Create new link only if it doesn't exist
                        ModSourceCodeLink::query()->create([
                            'mod_id' => $localMod->id,
                            'url' => $url,
                            'label' => '',
                        ]);
                    }
                }
            }
        });

        // Dispatch image jobs for mod thumbnails
        $activeHubMods->each(function (HubMod $hubMod) use ($localMods): void {
            if ($localMod = $localMods->get($hubMod->fileID)) {
                ImportHubImageJob::dispatch(
                    'mod_thumbnail',
                    $localMod->id,
                    $hubMod->toArray()
                )->onQueue('default');
            }
        });
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
            ->leftJoin('filebase1_file_option_value as option_values', function (JoinClause $join): void {
                $join->on('version.fileID', '=', 'option_values.fileID')
                    ->whereIn('option_values.optionID', [6, 2]); // VirusTotal links
            })
            ->leftJoin('wcf1_label_object as label', 'version.fileID', '=', 'label.objectID')
            ->leftJoin('wcf1_label as spt_version', 'label.labelID', '=', 'spt_version.labelID')
            ->groupBy('version.versionID')
            ->orderBy('version.versionID', 'desc')
            ->chunk(1000, function (Collection $records): void {
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
        // Get all available SPT versions for validation
        static $availableSptVersions = null;
        if ($availableSptVersions === null) {
            $availableSptVersions = SptVersion::allValidVersions();
        }

        // Prepare data for upsert.
        $modVersionData = [];

        $hubModVersions->each(function (HubModVersion $hubModVersion) use ($localMods, &$modVersionData, $availableSptVersions): void {
            try {
                $version = Version::cleanModImport($hubModVersion->versionNumber);
                $localMod = $localMods->get($hubModVersion->fileID);

                // If mod doesn't exist locally, skip this version.
                if (! $localMod) {
                    return;
                }

                $modId = $localMod->id;

                // Get and validate the SPT version constraint from hub tag
                $sptConstraint = $hubModVersion->getSptVersionConstraint();
                // Only store valid constraints for later processing
                if ($sptConstraint !== '' && $sptConstraint !== '0.0.0') {
                    $validatedConstraint = $this->validateSptConstraint($sptConstraint, $availableSptVersions);
                    if ($validatedConstraint) {
                        $this->sptVersionConstraints[$modId][$hubModVersion->versionID] = $validatedConstraint;
                    }
                }

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
                // Get existing hub_ids to determine which records to update vs. insert
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
     * Also parse SPT version compatibility from mod version descriptions.
     */
    private function processModVersionSptConstraints(): void
    {
        if (empty($this->sptVersionConstraints)) {
            return;
        }

        // Get all available SPT versions for validation
        $availableSptVersions = SptVersion::allValidVersions();

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

        // Process each mod's versions to apply SPT version constraints
        $this->applyParsedSptVersionConstraints($versionsGroupedByMod, $availableSptVersions, $now);

        ModVersion::withoutEvents(function () use ($versionsGroupedByMod, $availableSptVersions, $now): void {
            foreach ($versionsGroupedByMod as $modId => $modVersions) {
                $latestEnabledVersion = $modVersions->where('disabled', false)->first();
                if (! $latestEnabledVersion) {
                    continue;
                }

                $versionConstraintsForMod = $this->sptVersionConstraints[$modId] ?? null;
                if (! $versionConstraintsForMod) {
                    continue;
                }

                $constraintValue = null;
                foreach ($versionConstraintsForMod as $constraint) {
                    if ($constraint !== '' && $constraint !== '0.0.0') {
                        $constraintValue = $constraint;
                        break;
                    }
                }

                // Only update if there's a valid constraint value from the hub tag
                if ($constraintValue) {
                    // Validate the constraint against actual SPT versions
                    $validatedConstraint = $this->validateSptConstraint($constraintValue, $availableSptVersions);

                    if ($validatedConstraint && $latestEnabledVersion->spt_version_constraint !== $validatedConstraint) {
                        ModVersion::query()
                            ->where('id', $latestEnabledVersion->id)
                            ->update([
                                'spt_version_constraint' => $validatedConstraint,
                                'updated_at' => $now,
                            ]);
                    }
                }
            }
        });
    }

    /**
     * Parse SPT version compatibility from mod version description.
     *
     * @param  string  $description  The mod version description to parse
     * @param  array<string>  $availableSptVersions  List of available SPT versions to match against
     * @return string|null The parsed SPT version constraint or null if none found
     */
    private function parseSptVersionFromDescription(string $description, array $availableSptVersions): ?string
    {
        // Pattern to catch version numbers in various contexts
        // This will match:
        // - Any version number (3.7.0, 3.8, etc.) that appears in the text
        // - With optional 'v' prefix (v3.7.0)
        // - With .x or .X suffix (3.7.x)
        $versionPattern = '/\b(?:v)?([0-9]+\.[0-9]+(?:\.[0-9]+)?(?:\.[xX])?)\b/i';

        $foundVersions = [];

        if (preg_match_all($versionPattern, $description, $matches)) {
            foreach ($matches[1] as $versionNumber) {
                $normalizedVersion = $this->normalizeVersionString($versionNumber);
                if ($normalizedVersion === null) {
                    continue;
                }

                // Only consider versions that actually exist in our SPT versions database
                // or could resolve to an existing SPT version
                if ($this->matchesSptVersion($normalizedVersion, $availableSptVersions)) {
                    $foundVersions[] = $normalizedVersion;
                }
            }
        }

        // Remove duplicates
        $foundVersions = array_unique($foundVersions);

        if (count($foundVersions) === 0) {
            return null;
        }

        // If we have multiple versions, prefer the highest one that exists
        usort($foundVersions, 'version_compare');
        $bestVersion = end($foundVersions);

        // Parse the version to determine the constraint format
        $parts = explode('.', $bestVersion);

        // If we only have major.minor (2 parts), use tilde constraint for flexibility
        if (count($parts) === 2) {
            // Add .0 for a complete version and use tilde
            return '~'.$bestVersion.'.0';
        }

        // If we have a full version with patch (3+ parts), use exact constraint
        // No tilde needed when patch version is specified
        while (count($parts) < 3) {
            $parts[] = '0';
        }

        $normalizedVersion = implode('.', array_slice($parts, 0, 3));

        return $normalizedVersion;
    }

    /**
     * Check if a version matches or could resolve to an actual SPT version.
     *
     * @param  string  $version  The version number to check
     * @param  array<string>  $availableSptVersions  List of available SPT versions
     * @return bool True if this matches an SPT version
     */
    private function matchesSptVersion(string $version, array $availableSptVersions): bool
    {
        // Direct match - the version exists exactly
        if (in_array($version, $availableSptVersions, true)) {
            return true;
        }

        // Check if it's a major.minor that exists
        $parts = explode('.', $version);
        if (count($parts) === 2) {
            $majorMinor = $parts[0].'.'.$parts[1];

            // Check if any available version starts with this major.minor
            foreach ($availableSptVersions as $availableVersion) {
                if (str_starts_with($availableVersion, $majorMinor.'.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Normalize a version string to ensure it has proper format.
     *
     * @param  string  $version  The version string to normalize
     * @return string|null The normalized version or null if invalid
     */
    private function normalizeVersionString(string $version): ?string
    {
        // Remove any trailing .x or .X
        $version = preg_replace('/\.[xX]$/', '', $version);

        // Validate the version format
        if (! preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/', (string) $version)) {
            return null;
        }

        return $version;
    }

    /**
     * Validate an SPT constraint against available SPT versions.
     * Converts invalid constraints to valid ones when possible.
     *
     * @param  string  $constraint  The SPT version constraint to validate
     * @param  array<string>  $availableSptVersions  List of available SPT versions
     * @return string|null The validated constraint or null if invalid
     */
    private function validateSptConstraint(string $constraint, array $availableSptVersions): ?string
    {
        // Remove tilde if present to get the base version
        $baseVersion = ltrim($constraint, '~');
        $hasTilde = str_starts_with($constraint, '~');

        // Parse the version parts
        $parts = explode('.', $baseVersion);
        if (count($parts) < 2) {
            return null;
        }

        $major = $parts[0];
        $minor = $parts[1];
        $patch = $parts[2] ?? '0';

        // Check if this major.minor combination exists in available versions
        $majorMinorExists = false;
        $exactVersionExists = false;

        foreach ($availableSptVersions as $availableVersion) {
            if (str_starts_with($availableVersion, $major.'.'.$minor.'.')) {
                $majorMinorExists = true;
                if ($availableVersion === $major.'.'.$minor.'.'.$patch) {
                    $exactVersionExists = true;
                }
            }
        }

        // If the major.minor doesn't exist at all, the constraint is invalid
        if (! $majorMinorExists) {
            return null;
        }

        // If it has a tilde, it should be for major.minor flexibility
        if ($hasTilde) {
            // Tilde constraints should use .0 as the patch version
            return '~'.$major.'.'.$minor.'.0';
        }

        // For exact constraints, verify the exact version exists
        if ($exactVersionExists) {
            return $major.'.'.$minor.'.'.$patch;
        }

        // If exact version doesn't exist but major.minor does,
        // convert to a tilde constraint for flexibility
        return '~'.$major.'.'.$minor.'.0';
    }

    /**
     * Apply parsed SPT version constraints from mod version descriptions.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Collection<int, ModVersion>>  $versionsGroupedByMod  Mod versions grouped by mod ID
     * @param  array<string>  $availableSptVersions  Available SPT versions
     * @param  string  $now  Current timestamp
     */
    private function applyParsedSptVersionConstraints(\Illuminate\Support\Collection $versionsGroupedByMod, array $availableSptVersions, string $now): void
    {
        ModVersion::withoutEvents(function () use ($versionsGroupedByMod, $availableSptVersions, $now): void {
            foreach ($versionsGroupedByMod as $modVersions) {
                /** @var \Illuminate\Database\Eloquent\Collection<int, ModVersion> $modVersions */
                foreach ($modVersions as $modVersion) {
                    // Skip if this version already has a constraint
                    if (! empty($modVersion->spt_version_constraint)) {
                        continue;
                    }

                    // Skip if the version is disabled
                    if ($modVersion->disabled) {
                        continue;
                    }

                    // Skip if there's no description to parse
                    if (empty($modVersion->description)) {
                        continue;
                    }

                    // Try to parse SPT version from the description
                    $parsedConstraint = $this->parseSptVersionFromDescription(
                        $modVersion->description,
                        $availableSptVersions
                    );

                    if ($parsedConstraint) {
                        ModVersion::query()
                            ->where('id', $modVersion->id)
                            ->update([
                                'spt_version_constraint' => $parsedConstraint,
                                'updated_at' => $now,
                            ]);
                    }
                }
            }
        });
    }

    /**
     * Get all comments from the Hub database and pass them in batches to be processed.
     */
    private function getHubComments(): void
    {
        // Get object type IDs for file comments and user profile comments
        $objectTypeIds = DB::connection('hub')
            ->table('wcf1_object_type')
            ->whereIn('objectType', ['com.woltlab.filebase.fileComment', 'com.woltlab.wcf.user.profileComment'])
            ->pluck('objectTypeID')
            ->toArray();

        // Get specific object type IDs for filtering
        $fileCommentTypeId = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.filebase.fileComment')
            ->value('objectTypeID');

        $profileCommentTypeId = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.wcf.user.profileComment')
            ->value('objectTypeID');

        if (empty($objectTypeIds)) {
            return;
        }

        $processedCount = 0;
        DB::connection('hub')
            ->table('wcf1_comment')
            ->whereIn('objectTypeID', $objectTypeIds)
            ->orderBy('commentID')
            ->chunk(500, function (Collection $records) use (&$processedCount, $fileCommentTypeId, $profileCommentTypeId): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, CommentDto> $hubComments */
                $hubComments = $records->map(fn (object $record): CommentDto => CommentDto::fromArray((array) $record));

                $processedCount += $records->count();

                $this->processCommentBatch($hubComments, $fileCommentTypeId, $profileCommentTypeId);
            });

        // Process comment responses (replies)
        DB::connection('hub')
            ->table('wcf1_comment_response as cr')
            ->join('wcf1_comment as c', 'cr.commentID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->select('cr.*')
            ->orderBy('cr.responseID')
            ->chunk(500, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, CommentResponseDto> $hubCommentResponses */
                $hubCommentResponses = $records->map(fn (object $record): CommentResponseDto => CommentResponseDto::fromArray((array) $record));

                $this->processCommentResponseBatch($hubCommentResponses);
            });

        // Process comment likes/reactions
        // First get likes for main comments
        // Get the objectTypeID for comments in the like system
        $commentLikeObjectTypeId = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.wcf.comment')
            ->value('objectTypeID');

        $totalCommentLikes = DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment as c', 'l.objectID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->where('l.objectTypeID', $commentLikeObjectTypeId)
            ->count();

        DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment as c', 'l.objectID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->where('l.objectTypeID', $commentLikeObjectTypeId)
            ->select('l.*')
            ->orderBy('l.likeID')
            ->chunk(500, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, CommentLikeDto> $hubCommentLikes */
                $hubCommentLikes = $records->map(fn (object $record): CommentLikeDto => CommentLikeDto::fromArray((array) $record));

                $this->processCommentLikeBatch($hubCommentLikes);
            });

        // Then get likes for comment responses
        // Get ALL objectTypeIDs for comment responses from hub config (there can be multiple)
        $responseObjectTypeIds = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.wcf.comment.response')
            ->pluck('objectTypeID')
            ->toArray();

        $totalResponseLikes = DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment_response as cr', 'l.objectID', '=', 'cr.responseID')
            ->join('wcf1_comment as c', 'cr.commentID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->whereIn('l.objectTypeID', $responseObjectTypeIds)
            ->count();

        DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment_response as cr', 'l.objectID', '=', 'cr.responseID')
            ->join('wcf1_comment as c', 'cr.commentID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->whereIn('l.objectTypeID', $responseObjectTypeIds)
            ->select('l.*')
            ->orderBy('l.likeID')
            ->chunk(500, function (Collection $records): void {
                /** @var Collection<int, object> $records */
                /** @var Collection<int, CommentLikeDto> $hubCommentLikes */
                $hubCommentLikes = $records->map(fn (object $record): CommentLikeDto => CommentLikeDto::fromArray((array) $record));

                $this->processCommentResponseLikeBatch($hubCommentLikes);
            });

        // Clean up hard-deleted comments and reactions
        $this->removeHardDeletedComments($objectTypeIds);
        $this->removeHardDeletedCommentReactions($objectTypeIds);

        // Remove forge-only comments (comments created on the forge but not on the hub)
        $this->removeForgeOnlyComments();
        $this->removeForgeOnlyCommentReactions();

        // Reset comment reactions to hub values (remove any local reactions not present in hub)
        $this->resetCommentReactionsToHubValues($objectTypeIds);
    }

    /**
     * Process a batch of Hub comments.
     *
     * @param  Collection<int, CommentDto>  $hubComments
     */
    private function processCommentBatch(Collection $hubComments, int $fileCommentTypeId, int $profileCommentTypeId): void
    {

        $hubUserIds = $hubComments->pluck('userID')->filter()->unique();
        $hubObjectIds = $hubComments->pluck('objectID')->filter()->unique();

        // Combine user IDs and object IDs (for profile comments, objectID is the target user)
        $allUserIds = $hubUserIds->merge($hubObjectIds)->unique();

        /** @var EloquentCollection<int, User> $localUsers */
        $localUsers = User::query()->whereIn('hub_id', $allUserIds)->get()->keyBy('hub_id');

        // Get mod comments - filter by objectTypeID for file comments
        $modComments = $hubComments->filter(function (CommentDto $comment) use ($localUsers, $fileCommentTypeId): bool {

            // Only process if this is a file comment
            if ($comment->objectTypeID !== $fileCommentTypeId) {
                return false;
            }

            // Check if this is a file comment by looking for a corresponding mod
            $mod = Mod::query()->where('hub_id', $comment->objectID)->first();

            if ($mod === null) {
                return false;
            }

            if (! $localUsers->has($comment->userID)) {
                return false;
            }

            return true;
        });

        // Get user profile comments - filter by objectTypeID for profile comments
        $userComments = $hubComments->filter(function (CommentDto $comment) use ($localUsers, $profileCommentTypeId): bool {

            // Only process if this is a profile comment
            if ($comment->objectTypeID !== $profileCommentTypeId) {
                return false;
            }

            // Check if this is a user profile comment
            $targetUser = $localUsers->get($comment->objectID);

            if ($targetUser === null) {
                return false;
            }

            if (! $localUsers->has($comment->userID)) {
                return false;
            }

            return true;
        });

        // Process mod comments
        if ($modComments->isNotEmpty()) {
            $this->processModComments($modComments, $localUsers);
        }

        // Process user profile comments
        if ($userComments->isNotEmpty()) {
            $this->processUserComments($userComments, $localUsers);
        }
    }

    /**
     * Process mod comments.
     *
     * @param  Collection<int, CommentDto>  $modComments
     * @param  EloquentCollection<int, User>  $localUsers
     */
    private function processModComments(Collection $modComments, EloquentCollection $localUsers): void
    {
        $modHubIds = $modComments->pluck('objectID')->unique();

        /** @var EloquentCollection<int, Mod> $localMods */
        $localMods = Mod::query()->whereIn('hub_id', $modHubIds)->get()->keyBy('hub_id');

        $commentData = [];
        $modComments->each(function (CommentDto $hubComment) use ($localUsers, $localMods, &$commentData): void {
            $localUser = $localUsers->get($hubComment->userID);
            $localMod = $localMods->get($hubComment->objectID);

            if (! $localUser || ! $localMod) {
                return;
            }

            $commentData[] = [
                'hub_id' => $hubComment->commentID,
                'user_id' => $localUser->id,
                'commentable_type' => Mod::class,
                'commentable_id' => $localMod->id,
                'body' => $hubComment->getCleanMessage(),
                'user_ip' => '0.0.0.0', // Default IP for imported comments
                'user_agent' => 'Hub Import',
                'referrer' => '',
                'parent_id' => null,
                'root_id' => null,
                'spam_status' => 'clean',
                'spam_metadata' => null,
                'spam_checked_at' => null,
                'spam_recheck_count' => 0,
                'edited_at' => null,
                'deleted_at' => $hubComment->isDeleted() ? $hubComment->getCreatedAt() : null,
                'pinned_at' => $hubComment->isPinned() ? $hubComment->getCreatedAt() : null,
                'created_at' => $hubComment->getCreatedAt(),
                'updated_at' => $hubComment->getCreatedAt(),
            ];
        });

        if (! empty($commentData)) {
            $this->insertComments($commentData);
        }
    }

    /**
     * Process user profile comments.
     *
     * @param  Collection<int, CommentDto>  $userComments
     * @param  EloquentCollection<int, User>  $localUsers
     */
    private function processUserComments(Collection $userComments, EloquentCollection $localUsers): void
    {
        $commentData = [];
        $userComments->each(function (CommentDto $hubComment) use ($localUsers, &$commentData): void {
            $commentUser = $localUsers->get($hubComment->userID);
            $targetUser = $localUsers->get($hubComment->objectID);

            if (! $commentUser || ! $targetUser) {
                return;
            }

            $commentData[] = [
                'hub_id' => $hubComment->commentID,
                'user_id' => $commentUser->id,
                'commentable_type' => User::class,
                'commentable_id' => $targetUser->id,
                'body' => $hubComment->getCleanMessage(),
                'user_ip' => '0.0.0.0', // Default IP for imported comments
                'user_agent' => 'Hub Import',
                'referrer' => '',
                'parent_id' => null,
                'root_id' => null,
                'spam_status' => 'clean',
                'spam_metadata' => null,
                'spam_checked_at' => null,
                'spam_recheck_count' => 0,
                'edited_at' => null,
                'deleted_at' => $hubComment->isDeleted() ? $hubComment->getCreatedAt() : null,
                'pinned_at' => $hubComment->isPinned() ? $hubComment->getCreatedAt() : null,
                'created_at' => $hubComment->getCreatedAt(),
                'updated_at' => $hubComment->getCreatedAt(),
            ];
        });

        if (! empty($commentData)) {
            $this->insertComments($commentData);
        }
    }

    /**
     * Process a batch of Hub comment responses (replies).
     *
     * @param  Collection<int, CommentResponseDto>  $hubCommentResponses
     */
    private function processCommentResponseBatch(Collection $hubCommentResponses): void
    {
        $hubUserIds = $hubCommentResponses->pluck('userID')->filter()->unique();
        $hubCommentIds = $hubCommentResponses->pluck('commentID')->unique();

        /** @var EloquentCollection<int, User> $localUsers */
        $localUsers = User::query()->whereIn('hub_id', $hubUserIds)->get()->keyBy('hub_id');

        /** @var EloquentCollection<int, Comment> $parentComments */
        $parentComments = Comment::query()->whereIn('hub_id', $hubCommentIds)->get()->keyBy('hub_id');

        $commentData = [];
        $hubCommentResponses->each(function (CommentResponseDto $hubResponse) use ($localUsers, $parentComments, &$commentData): void {
            $localUser = $localUsers->get($hubResponse->userID);
            $parentComment = $parentComments->get($hubResponse->commentID);

            if (! $localUser || ! $parentComment) {
                return;
            }

            $commentData[] = [
                'hub_id' => -$hubResponse->responseID, // Negative to avoid collision with comment IDs
                'user_id' => $localUser->id,
                'commentable_type' => $parentComment->commentable_type,
                'commentable_id' => $parentComment->commentable_id,
                'body' => $hubResponse->getCleanMessage(),
                'user_ip' => '0.0.0.0', // Default IP for imported comments
                'user_agent' => 'Hub Import',
                'referrer' => '',
                'parent_id' => $parentComment->id,
                'root_id' => $parentComment->root_id ?? $parentComment->id,
                'spam_status' => 'clean',
                'spam_metadata' => null,
                'spam_checked_at' => null,
                'spam_recheck_count' => 0,
                'edited_at' => null,
                'deleted_at' => $hubResponse->isDeleted() ? $hubResponse->getCreatedAt() : null,
                'pinned_at' => null, // Replies cannot be pinned
                'created_at' => $hubResponse->getCreatedAt(),
                'updated_at' => $hubResponse->getCreatedAt(),
            ];
        });

        if (! empty($commentData)) {
            $this->insertComments($commentData);
        }
    }

    /**
     * Process a batch of comment likes/reactions.
     *
     * @param  Collection<int, CommentLikeDto>  $hubCommentLikes
     */
    private function processCommentLikeBatch(Collection $hubCommentLikes): void
    {
        $hubUserIds = $hubCommentLikes->pluck('userID')->filter()->unique();

        /** @var EloquentCollection<int, User> $localUsers */
        $localUsers = User::query()->whereIn('hub_id', $hubUserIds)->get()->keyBy('hub_id');

        // Get comment IDs and response IDs to find local comments
        $hubCommentIds = $hubCommentLikes->pluck('objectID')->unique();

        /** @var EloquentCollection<int, Comment> $localComments */
        $localComments = Comment::query()->whereIn('hub_id', $hubCommentIds)->get()->keyBy('hub_id');

        $reactionData = [];
        $filteredOutCount = 0;

        $hubCommentLikes->each(function (CommentLikeDto $hubLike) use ($localUsers, $localComments, &$reactionData, &$filteredOutCount): void {
            $localUser = $localUsers->get($hubLike->userID);
            $localComment = $localComments->get($hubLike->objectID);

            if (! $localUser || ! $localComment) {
                $filteredOutCount++;

                return;
            }

            // Convert all reactions to "like" as specified
            $reactionData[] = [
                'hub_id' => $hubLike->likeID,
                'comment_id' => $localComment->id,
                'user_id' => $localUser->id,
                'created_at' => $hubLike->getCreatedAt(),
                'updated_at' => $hubLike->getCreatedAt(),
            ];
        });

        if (! empty($reactionData)) {
            $this->insertCommentReactions($reactionData);
        }
    }

    /**
     * Process a batch of comment response likes/reactions.
     *
     * @param  Collection<int, CommentLikeDto>  $hubCommentLikes
     */
    private function processCommentResponseLikeBatch(Collection $hubCommentLikes): void
    {
        $hubUserIds = $hubCommentLikes->pluck('userID')->filter()->unique();

        /** @var EloquentCollection<int, User> $localUsers */
        $localUsers = User::query()->whereIn('hub_id', $hubUserIds)->get()->keyBy('hub_id');

        // Get response IDs and convert to negative hub_ids to find local comments
        $hubResponseIds = $hubCommentLikes->pluck('objectID')->unique()->map(fn (int $id): int => -$id);

        /** @var EloquentCollection<int, Comment> $localComments */
        $localComments = Comment::query()->whereIn('hub_id', $hubResponseIds)->get()->keyBy('hub_id');

        $reactionData = [];
        $filteredOutCount = 0;

        $hubCommentLikes->each(function (CommentLikeDto $hubLike) use ($localUsers, $localComments, &$reactionData, &$filteredOutCount): void {

            $localUser = $localUsers->get($hubLike->userID);
            $localComment = $localComments->get(-$hubLike->objectID); // Use negative ID to find a response

            if (! $localUser || ! $localComment) {
                $filteredOutCount++;

                return;
            }

            // Convert all reactions to "like" as specified
            $reactionData[] = [
                'hub_id' => $hubLike->likeID,
                'comment_id' => $localComment->id,
                'user_id' => $localUser->id,
                'created_at' => $hubLike->getCreatedAt(),
                'updated_at' => $hubLike->getCreatedAt(),
            ];
        });

        if (! empty($reactionData)) {
            $this->insertCommentReactions($reactionData);
        }
    }

    /**
     * Insert comments into the database using bulk operations.
     *
     * @param  array<int, array<string, mixed>>  $commentData
     */
    private function insertComments(array $commentData): void
    {
        Comment::withoutEvents(function () use ($commentData): void {
            // Get existing hub_ids to determine which records to update vs. insert
            $hubIds = collect($commentData)->pluck('hub_id');
            $existingComments = Comment::query()->whereIn('hub_id', $hubIds)->pluck('hub_id')->toArray();

            // Split data into inserts (new) and updates (existing)
            $insertData = [];
            $updateData = [];

            foreach ($commentData as $comment) {
                if (in_array($comment['hub_id'], $existingComments)) {
                    $updateData[] = $comment;
                } else {
                    $insertData[] = $comment;
                }
            }

            // Insert new comments (only allocates IDs for actual new records)
            if (! empty($insertData)) {
                Comment::query()->insert($insertData);
            }

            // Update existing comments (no ID allocation)
            foreach ($updateData as $comment) {
                Comment::query()->where('hub_id', $comment['hub_id'])->update([
                    'user_id' => $comment['user_id'],
                    'commentable_type' => $comment['commentable_type'],
                    'commentable_id' => $comment['commentable_id'],
                    'body' => $comment['body'],
                    'user_ip' => $comment['user_ip'],
                    'user_agent' => $comment['user_agent'],
                    'referrer' => $comment['referrer'],
                    'parent_id' => $comment['parent_id'],
                    'root_id' => $comment['root_id'],
                    'spam_status' => $comment['spam_status'],
                    'spam_metadata' => $comment['spam_metadata'],
                    'spam_checked_at' => $comment['spam_checked_at'],
                    'spam_recheck_count' => $comment['spam_recheck_count'],
                    'edited_at' => $comment['edited_at'],
                    'deleted_at' => $comment['deleted_at'],
                    'pinned_at' => $comment['pinned_at'],
                    'created_at' => $comment['created_at'],
                    'updated_at' => $comment['updated_at'],
                ]);
            }
        });
    }

    /**
     * Insert comment reactions into the database using bulk operations.
     *
     * @param  array<int, array<string, mixed>>  $reactionData
     */
    private function insertCommentReactions(array $reactionData): void
    {

        CommentReaction::withoutEvents(function () use ($reactionData): void {
            // Get existing hub_ids to determine which records to update vs. insert
            $hubIds = collect($reactionData)->pluck('hub_id')->filter();
            $existingReactionsByHubId = CommentReaction::query()
                ->whereIn('hub_id', $hubIds)
                ->pluck('hub_id')
                ->toArray();

            // Get existing user+comment combinations to avoid unique constraint violations
            $userCommentPairs = collect($reactionData)
                ->map(fn (array $reaction): array => ['user_id' => $reaction['user_id'], 'comment_id' => $reaction['comment_id']])
                ->unique(fn (array $pair): string => $pair['user_id'].'-'.$pair['comment_id']);

            $existingReactionsByUserComment = CommentReaction::query()
                ->whereIn('user_id', $userCommentPairs->pluck('user_id'))
                ->whereIn('comment_id', $userCommentPairs->pluck('comment_id'))
                ->get()
                ->mapWithKeys(fn (CommentReaction $reaction): array => [$reaction->user_id.'-'.$reaction->comment_id => $reaction])
                ->toArray();

            // Split data into inserts (new) and updates (existing)
            $insertData = [];
            $updateData = [];

            foreach ($reactionData as $reaction) {
                $userCommentKey = $reaction['user_id'].'-'.$reaction['comment_id'];
                $hubIdExists = in_array($reaction['hub_id'], $existingReactionsByHubId);
                $userCommentExists = isset($existingReactionsByUserComment[$userCommentKey]);

                if ($hubIdExists || $userCommentExists) {
                    // Update the existing reaction (prefer hub_id match, fallback to user+comment match)
                    $updateData[] = $reaction;
                } else {
                    // Insert a new reaction
                    $insertData[] = $reaction;
                }
            }

            // Insert new reactions (only allocates IDs for actual new records)
            if (! empty($insertData)) {
                // Remove any potential duplicates within the insert batch itself
                $uniqueInsertData = collect($insertData)
                    ->unique(fn (array $reaction): string => $reaction['user_id'].'-'.$reaction['comment_id'])
                    ->values()
                    ->toArray();

                if (! empty($uniqueInsertData)) {
                    CommentReaction::query()->insert($uniqueInsertData);
                }
            }

            // Update existing reactions
            foreach ($updateData as $reaction) {
                $userCommentKey = $reaction['user_id'].'-'.$reaction['comment_id'];

                if (in_array($reaction['hub_id'], $existingReactionsByHubId)) {
                    // Update by hub_id
                    CommentReaction::query()->where('hub_id', $reaction['hub_id'])->update([
                        'comment_id' => $reaction['comment_id'],
                        'user_id' => $reaction['user_id'],
                        'created_at' => $reaction['created_at'],
                        'updated_at' => $reaction['updated_at'],
                    ]);
                } elseif (isset($existingReactionsByUserComment[$userCommentKey])) {
                    // Update by user+comment combination
                    CommentReaction::query()
                        ->where('user_id', $reaction['user_id'])
                        ->where('comment_id', $reaction['comment_id'])
                        ->update([
                            'hub_id' => $reaction['hub_id'],
                            'created_at' => $reaction['created_at'],
                            'updated_at' => $reaction['updated_at'],
                        ]);
                }
            }
        });
    }

    /**
     * Remove comments that have been hard-deleted from the Hub database.
     *
     * @param  array<int>  $objectTypeIds
     */
    private function removeHardDeletedComments(array $objectTypeIds): void
    {
        if (empty($objectTypeIds)) {
            return;
        }

        // Get all comment IDs that still exist in the hub for the relevant object types
        $existingHubCommentIds = DB::connection('hub')
            ->table('wcf1_comment')
            ->whereIn('objectTypeID', $objectTypeIds)
            ->pluck('commentID')
            ->toArray();

        // Get all response IDs that still exist in the hub
        $existingHubResponseIds = DB::connection('hub')
            ->table('wcf1_comment_response as cr')
            ->join('wcf1_comment as c', 'cr.commentID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->pluck('cr.responseID')
            ->toArray();

        // Convert response IDs to negative values to match how they're stored locally
        $existingNegativeResponseIds = array_map(fn (int $id): int => -$id, $existingHubResponseIds);

        // Combine comment IDs (positive) and response IDs (negative)
        $allExistingHubIds = array_merge($existingHubCommentIds, $existingNegativeResponseIds);

        if (empty($allExistingHubIds)) {
            // If no comments exist in the hub, delete all local comments with hub_id
            Comment::query()->whereNotNull('hub_id')->delete();
        } else {
            // Get all local hub_ids first, then find which ones to delete
            $localHubIds = Comment::query()
                ->whereNotNull('hub_id')
                ->pluck('hub_id')
                ->toArray();

            // Find hub_ids that exist locally but not in the hub
            $hubIdsToDelete = array_diff($localHubIds, $allExistingHubIds);

            if (! empty($hubIdsToDelete)) {
                // Chunk the delete operation to avoid MySQL's 65,535 placeholder limits
                $chunkSize = 50000; // Well below the 65,535 limits to be safe
                $chunks = array_chunk($hubIdsToDelete, $chunkSize);

                foreach ($chunks as $chunk) {
                    Comment::query()->whereIn('hub_id', $chunk)->delete();
                }
            }
        }
    }

    /**
     * Remove comment reactions that have been hard-deleted from the Hub database.
     *
     * @param  array<int>  $objectTypeIds
     */
    private function removeHardDeletedCommentReactions(array $objectTypeIds): void
    {
        if (empty($objectTypeIds)) {
            return;
        }

        // Get all like IDs that still exist in the hub for comments
        $commentLikeObjectTypeId = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.wcf.comment')
            ->value('objectTypeID');

        $existingCommentLikeIds = DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment as c', 'l.objectID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->where('l.objectTypeID', $commentLikeObjectTypeId)
            ->pluck('l.likeID')
            ->toArray();

        // Get all like IDs that still exist in the hub for comment responses
        // Get ALL objectTypeIDs for comment responses from hub config (there can be multiple)
        $responseObjectTypeIds = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.wcf.comment.response')
            ->pluck('objectTypeID')
            ->toArray();

        $existingResponseLikeIds = DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment_response as cr', 'l.objectID', '=', 'cr.responseID')
            ->join('wcf1_comment as c', 'cr.commentID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->whereIn('l.objectTypeID', $responseObjectTypeIds)
            ->pluck('l.likeID')
            ->toArray();

        // Combine both arrays - these are all the hub like IDs that should still exist
        $allExistingLikeIds = array_merge($existingCommentLikeIds, $existingResponseLikeIds);

        if (empty($allExistingLikeIds)) {
            // If no likes exist in hub, delete all reactions with hub_id (imported from hub)
            CommentReaction::query()->whereNotNull('hub_id')->delete();
        } else {
            // Get all local hub_ids first, then find which ones to delete
            $localHubIds = CommentReaction::query()
                ->whereNotNull('hub_id')
                ->pluck('hub_id')
                ->toArray();

            // Find hub_ids that exist locally but not in the hub
            $hubIdsToDelete = array_diff($localHubIds, $allExistingLikeIds);

            if (! empty($hubIdsToDelete)) {
                // Chunk the delete operation to avoid MySQL's 65,535 placeholder limits
                $chunkSize = 50000; // Well below the 65,535 limits to be safe
                $chunks = array_chunk($hubIdsToDelete, $chunkSize);

                foreach ($chunks as $chunk) {
                    CommentReaction::query()->whereIn('hub_id', $chunk)->delete();
                }
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

    /**
     * Remove mods that do not have a hub id. This ensures that anything uploaded directly to the Forge
     * is removed as "testing" data.
     */
    private function removeModsWithoutHubVersions(): void
    {
        Mod::query()->whereNull('hub_id')->delete();
    }

    /**
     * Remove comments that were created on the forge but don't exist on the hub.
     * This allows for testing the forge without worrying about cleanup.
     */
    private function removeForgeOnlyComments(): void
    {
        // Delete all comments that don't have a hub_id (created directly on forge)
        Comment::query()->whereNull('hub_id')->delete();
    }

    /**
     * Remove comment reactions that were created on the forge but don't exist on the hub.
     * This allows for testing the forge without worrying about cleanup.
     */
    private function removeForgeOnlyCommentReactions(): void
    {
        // Delete all comment reactions that don't have a hub_id (created directly on forge)
        CommentReaction::query()->whereNull('hub_id')->delete();
    }

    /**
     * Reset comment reactions to hub values by removing any local reactions
     * that don't match what exists in the hub.
     *
     * @param  array<int>  $objectTypeIds
     */
    private function resetCommentReactionsToHubValues(array $objectTypeIds): void
    {
        if (empty($objectTypeIds)) {
            return;
        }

        // Get all existing hub reaction IDs that should exist after import
        $commentLikeObjectTypeId = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.wcf.comment')
            ->value('objectTypeID');

        $existingCommentLikeIds = DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment as c', 'l.objectID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->where('l.objectTypeID', $commentLikeObjectTypeId)
            ->pluck('l.likeID')
            ->toArray();

        // Get response reaction IDs
        $responseObjectTypeIds = DB::connection('hub')
            ->table('wcf1_object_type')
            ->where('objectType', 'com.woltlab.wcf.comment.response')
            ->pluck('objectTypeID')
            ->toArray();

        $existingResponseLikeIds = DB::connection('hub')
            ->table('wcf1_like as l')
            ->join('wcf1_comment_response as cr', 'l.objectID', '=', 'cr.responseID')
            ->join('wcf1_comment as c', 'cr.commentID', '=', 'c.commentID')
            ->whereIn('c.objectTypeID', $objectTypeIds)
            ->whereIn('l.objectTypeID', $responseObjectTypeIds)
            ->pluck('l.likeID')
            ->toArray();

        $allValidHubIds = array_merge($existingCommentLikeIds, $existingResponseLikeIds);

        if (empty($allValidHubIds)) {
            // If no reactions should exist from hub, remove all hub-imported reactions
            CommentReaction::query()
                ->whereNotNull('hub_id')
                ->delete();
        } else {
            // Remove any reactions with hub_ids that are no longer valid
            CommentReaction::query()
                ->whereNotNull('hub_id')
                ->whereNotIn('hub_id', $allValidHubIds)
                ->delete();
        }
    }
}
