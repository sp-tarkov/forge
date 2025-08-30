<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SpamStatus;
use App\Enums\TrackingEventType;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Models\UserRole;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\progress;

class DatabaseSeeder extends Seeder
{
    private Generator $faker;

    /**
     * Create a new seeder instance.
     */
    public function __construct()
    {
        $this->faker = \Faker\Factory::create();
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Increase memory limit for seeding
        ini_set('memory_limit', '512M');

        // Disable ALL events globally during seeding to prevent job dispatch
        Model::unsetEventDispatcher();

        // Disable Scout search syncing for models that use Searchable trait
        Mod::withoutSyncingToSearch(function () {
            User::withoutSyncingToSearch(function () {
                $this->runSeeding();
            });
        });

        // Re-enable events by setting a new event dispatcher
        Model::setEventDispatcher(app('events'));

        Artisan::call('app:search-sync');
        Artisan::call('app:resolve-versions');
        Artisan::call('app:count-mods');
        Artisan::call('app:update-downloads');
        $this->command->outputComponents()->info('Jobs added to queue. Ensure queue is running!');

        Artisan::call('cache:clear');
        $this->command->outputComponents()->success('Cache cleared');

        $this->command->outputComponents()->success('Database seeding complete');
    }

    /**
     * Run the actual seeding logic.
     */
    private function runSeeding(): void
    {
        // How many of each entity to create.
        $counts = [
            'license' => 10,
            'administrator' => 5,
            'moderator' => 5,
            'user' => 100,
            'mod' => 200,
            'modVersion' => 1500,
            'trackingEvents' => 800,
        ];

        // Licenses
        $licenses = License::factory($counts['license'])->create();

        // Administrator Users
        $administratorRole = UserRole::factory()->administrator()->create();
        $testAccount = User::factory()->for($administratorRole, 'role')->create([
            'email' => 'test@example.com',
        ]);
        User::factory($counts['administrator'] - 1)->for($administratorRole, 'role')->create();

        $this->command->outputComponents()->info("Test account created: {$testAccount->email}");

        // Moderator Users
        $moderatorRole = UserRole::factory()->moderator()->create();
        User::factory($counts['moderator'])->for($moderatorRole, 'role')->create();

        // Users
        progress(
            label: 'Adding Users...',
            steps: $counts['user'],
            callback: fn () => User::factory()->create()
        );

        // All Users
        $allUsers = User::all();

        // User Follows
        progress(
            label: 'adding user follows ...',
            steps: $allUsers,
            callback: function ($user) use ($allUsers, $testAccount) {
                // Special handling for test account
                if ($user->id === $testAccount->id) {
                    // Test account should have exactly 15 followers and follow exactly 15 users
                    $otherUsers = $allUsers->where('id', '!=', $testAccount->id);

                    // Give test account 15 followers
                    $followers = $otherUsers->random(15)->pluck('id')->toArray();
                    $user->followers()->attach($followers);

                    // Make test account follow 15 different users (avoiding overlap where possible)
                    $following = $otherUsers->whereNotIn('id', $followers)->random(min(15, $otherUsers->whereNotIn('id', $followers)->count()))->pluck('id')->toArray();
                    if (count($following) < 15) {
                        // If we don't have enough non-followers, fill from all other users
                        $remaining = $otherUsers->whereNotIn('id', $following)->random(15 - count($following))->pluck('id')->toArray();
                        $following = array_merge($following, $remaining);
                    }
                    $user->following()->attach($following);

                    return;
                }

                // Regular random follow logic for other users
                $hasFollowers = rand(0, 100) < 70; // 70% chance to have followers
                $isFollowing = rand(0, 100) < 70; // 70% chance to be following other users

                if ($hasFollowers) {
                    $followers = $allUsers->random(rand(1, 10))->pluck('id')->toArray();
                    $user->followers()->attach($followers);
                }

                if ($isFollowing) {
                    $following = $allUsers->random(rand(1, 10))->pluck('id')->toArray();
                    $user->following()->attach($following);
                }
            });

        // Mods
        $mods = collect(progress(
            label: 'Adding Mods...',
            steps: $counts['mod'],
            callback: fn () => Mod::factory()->recycle([$licenses])->create()
        ));

        // Attach users to mods
        progress(
            label: 'Attaching users to mods...',
            steps: $mods,
            callback: function (Mod $mod, Progress $progress) use ($allUsers) {
                $userIds = $allUsers->random(rand(0, 2))->pluck('id')->toArray();
                if (count($userIds)) {
                    $mod->authors()->attach($userIds);
                }
            }
        );

        // Add mod versions, assigning them to the mods we just created
        $modVersions = collect(progress(
            label: 'Adding Mod Versions...',
            steps: $counts['modVersion'],
            callback: fn () => ModVersion::factory()->recycle([$mods])->create()
        ));

        // Add mod dependencies to *some* mod versions
        progress(
            label: 'Adding Mod Dependencies...',
            steps: $modVersions,
            callback: function (ModVersion $modVersion, Progress $progress) use ($mods) {
                // 70% chance has no dependencies
                if (rand(0, 9) >= 3) {
                    return;
                }

                // Choose 1-3 random mods to be dependencies.
                $dependencyMods = $mods->random(rand(1, 3));
                foreach ($dependencyMods as $dependencyMod) {
                    ModDependency::factory()->recycle([$modVersion, $dependencyMod])->create();
                }
            }
        );

        // Add comments to mods
        progress(
            label: 'Adding Comments...',
            steps: $mods,
            callback: function (Mod $mod, Progress $progress) use ($allUsers) {
                // Create 1-20 parent comments with varied spam statuses
                $parentCommentCount = rand(1, 20);

                for ($i = 0; $i < $parentCommentCount; $i++) {
                    // Determine spam status and deletion status
                    $spamStatus = $this->getRandomSpamStatus();
                    $isDeleted = rand(0, 100) < 10; // 10% chance to be deleted

                    $commentData = [
                        'spam_status' => $spamStatus,
                    ];
                    if ($isDeleted) {
                        $commentData['deleted_at'] = now()->subDays(rand(1, 30));
                    }

                    $comment = Comment::factory()
                        ->recycle([$mod])
                        ->recycle($allUsers)
                        ->create($commentData);

                    // For each comment, 30% chance to have replies
                    if (rand(0, 9) < 3) {
                        // Create 1-4 replies to the parent comment
                        $replyCount = rand(1, 4);

                        for ($j = 0; $j < $replyCount; $j++) {
                            $replySpamStatus = $this->getRandomSpamStatus();
                            $replyIsDeleted = rand(0, 100) < 8; // 8% chance for replies to be deleted

                            $replyData = [
                                'spam_status' => $replySpamStatus,
                            ];
                            if ($replyIsDeleted) {
                                $replyData['deleted_at'] = now()->subDays(rand(1, 15));
                            }

                            $firstLevelReply = Comment::factory()
                                ->reply($comment)
                                ->recycle($allUsers)
                                ->create($replyData);

                            // For each first-level reply, 40% chance to have nested replies
                            if (rand(0, 9) < 4) {
                                // Create 1-2 nested replies
                                $nestedReplyCount = rand(1, 2);

                                for ($k = 0; $k < $nestedReplyCount; $k++) {
                                    $nestedSpamStatus = $this->getRandomSpamStatus();
                                    $nestedIsDeleted = rand(0, 100) < 5; // 5% chance for nested replies to be deleted

                                    $nestedData = [
                                        'spam_status' => $nestedSpamStatus,
                                    ];
                                    if ($nestedIsDeleted) {
                                        $nestedData['deleted_at'] = now()->subDays(rand(1, 7));
                                    }

                                    Comment::factory()
                                        ->reply($firstLevelReply)
                                        ->recycle($allUsers)
                                        ->create($nestedData);
                                }
                            }
                        }
                    }
                }
            }
        );

        // Add reactions to comments
        progress(
            label: 'Adding Comment Reactions...',
            steps: Comment::all(),
            callback: function (Comment $comment, Progress $progress) use ($allUsers) {
                // 40% chance to have reactions
                if (rand(0, 9) < 4) {
                    // Add 1-5 reactions from different users
                    $reactingUsers = $allUsers->random(rand(1, 5));
                    foreach ($reactingUsers as $user) {
                        CommentReaction::factory()
                            ->recycle([$comment])
                            ->recycle([$user])
                            ->create();
                    }
                }
            }
        );

        // Generate realistic tracking events
        progress(
            label: 'Adding Tracking Events...',
            steps: $counts['trackingEvents'],
            callback: function (int $step) use ($allUsers, $mods, $modVersions) {
                $eventType = $this->getRandomEventType();
                $user = null;
                $trackable = null;

                // 70% chance to be an authenticated user event
                if (rand(0, 9) < 7) {
                    $user = $allUsers->random();
                }

                // Get a trackable model for events that require one
                if ($eventType->requiresTrackable()) {
                    $trackable = $this->getTrackableForEventType($eventType, $mods, $modVersions);
                }

                // Create the tracking event
                $trackingEvent = TrackingEvent::factory()
                    ->eventType($eventType)
                    ->make([
                        'visitor_type' => $user ? User::class : null,
                        'visitor_id' => $user?->id,
                        'created_at' => $this->getRandomTimestamp(),
                    ]);

                if ($trackable) {
                    $trackingEvent->visitable_type = get_class($trackable);
                    $trackingEvent->visitable_id = $trackable->getKey();
                }

                $trackingEvent->saveQuietly();
            }
        );

        // Load the content of the tests/Mock/MarkdownContent.md and create a new Mod with the content as the description
        $mod = Mod::factory()->hasVersions(3)->create([
            'name' => 'Markdown Test',
            'slug' => 'markdown-test',
            'description' => file_get_contents(__DIR__.'/../../resources/markdown/exampleModDescription.md'),
        ]);

        $this->command->outputComponents()->success('Initial seeding complete');
    }

    /**
     * Get a random spam status with weighted distribution.
     */
    private function getRandomSpamStatus(): SpamStatus
    {
        $random = rand(1, 100);

        // 85% clean, 10% pending, 5% spam
        if ($random <= 85) {
            return SpamStatus::CLEAN;
        } elseif ($random <= 95) {
            return SpamStatus::PENDING;
        } else {
            return SpamStatus::SPAM;
        }
    }

    /**
     * Get a random tracking event type with realistic distribution.
     */
    private function getRandomEventType(): TrackingEventType
    {
        $random = rand(1, 100);

        if ($random <= 40) {
            // 40% page visits and downloads (most common)
            return TrackingEventType::MOD_DOWNLOAD;
        } elseif ($random <= 60) {
            // 20% authentication events
            return $this->faker->randomElement([
                TrackingEventType::LOGIN,
                TrackingEventType::LOGOUT,
                TrackingEventType::REGISTER,
            ]);
        } elseif ($random <= 80) {
            // 20% comment interactions
            return $this->faker->randomElement([
                TrackingEventType::COMMENT_CREATE,
                TrackingEventType::COMMENT_LIKE,
                TrackingEventType::COMMENT_EDIT,
                TrackingEventType::COMMENT_DELETE,
            ]);
        } else {
            // 20% other events (mod management, versions, etc.)
            return $this->faker->randomElement([
                TrackingEventType::MOD_CREATE,
                TrackingEventType::MOD_EDIT,
                TrackingEventType::VERSION_CREATE,
                TrackingEventType::VERSION_EDIT,
                TrackingEventType::PASSWORD_CHANGE,
            ]);
        }
    }

    /**
     * Get a trackable model for the given event type.
     *
     * @param  \Illuminate\Support\Collection<int, Mod>  $mods
     * @param  \Illuminate\Support\Collection<int, ModVersion>  $modVersions
     */
    private function getTrackableForEventType(TrackingEventType $eventType, $mods, $modVersions): ?Model
    {
        return match ($eventType) {
            TrackingEventType::MOD_DOWNLOAD,
            TrackingEventType::MOD_CREATE,
            TrackingEventType::MOD_EDIT,
            TrackingEventType::MOD_DELETE,
            TrackingEventType::MOD_REPORT => $mods->random(),

            TrackingEventType::VERSION_CREATE,
            TrackingEventType::VERSION_EDIT,
            TrackingEventType::VERSION_DELETE => $modVersions->random(),

            TrackingEventType::COMMENT_CREATE,
            TrackingEventType::COMMENT_EDIT,
            TrackingEventType::COMMENT_DELETE,
            TrackingEventType::COMMENT_LIKE,
            TrackingEventType::COMMENT_UNLIKE,
            TrackingEventType::COMMENT_REPORT => Comment::inRandomOrder()->first(),

            default => null,
        };
    }

    /**
     * Get a random timestamp with realistic distribution.
     */
    private function getRandomTimestamp(): \DateTime
    {
        $random = rand(1, 100);

        // Weight recent events more heavily for realistic analytics
        if ($random <= 30) {
            // 30% in the last week
            return $this->faker->dateTimeBetween('-1 week', 'now');
        } elseif ($random <= 60) {
            // 30% in the last month
            return $this->faker->dateTimeBetween('-1 month', '-1 week');
        } elseif ($random <= 85) {
            // 25% in the last 3 months
            return $this->faker->dateTimeBetween('-3 months', '-1 month');
        } else {
            // 15% older than 3 months (up to 6 months)
            return $this->faker->dateTimeBetween('-6 months', '-3 months');
        }
    }
}
