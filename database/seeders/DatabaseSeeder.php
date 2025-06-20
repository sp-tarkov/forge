<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\progress;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Increase memory limit for seeding
        ini_set('memory_limit', '512M');

        // How many of each entity to create.
        $counts = [
            'license' => 10,
            'administrator' => 5,
            'moderator' => 5,
            'user' => 100,
            'mod' => 200,
            'modVersion' => 1500,
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
            callback: function ($user) use ($allUsers) {
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

        // Add mod versions, assigning them to the mods we just created.
        $modVersions = collect(progress(
            label: 'Adding Mod Versions...',
            steps: $counts['modVersion'],
            callback: fn () => ModVersion::factory()->recycle([$mods])->create()
        ));

        // Add mod dependencies to *some* mod versions.
        progress(
            label: 'Adding Mod Dependencies...',
            steps: $modVersions,
            callback: function (ModVersion $modVersion, Progress $progress) use ($mods) {
                // 70% chance to not have dependencies
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
                // Create 1-20 parent comments
                $comments = Comment::factory(rand(1, 20))
                    ->recycle([$mod])
                    ->recycle($allUsers)
                    ->create();

                // For each comment, 30% chance to have replies
                foreach ($comments as $comment) {
                    if (rand(0, 9) < 3) { // 30% chance
                        // Create 1-4 replies to the parent comment
                        $firstLevelReplies = Comment::factory(rand(1, 4))
                            ->reply($comment)
                            ->recycle($allUsers)
                            ->create();

                        // For each first-level reply, 40% chance to have nested replies
                        foreach ($firstLevelReplies as $reply) {
                            if (rand(0, 9) < 4) { // 40% chance
                                // Create 1-2 nested replies
                                Comment::factory(rand(1, 2))
                                    ->reply($reply)
                                    ->recycle($allUsers)
                                    ->create();
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

        // Load the content of the tests/Mock/MarkdownContent.md and create a new Mod with the content as the description.
        $mod = Mod::factory()->hasVersions(3)->create([
            'name' => 'Markdown Test',
            'slug' => 'markdown-test',
            'description' => file_get_contents(__DIR__.'/../../resources/markdown/exampleModDescription.md'),
        ]);

        $this->command->outputComponents()->success('Initial seeding complete');

        Artisan::call('app:search-sync');
        Artisan::call('app:resolve-versions');
        Artisan::call('app:count-mods');
        Artisan::call('app:update-downloads');
        $this->command->outputComponents()->warn('Jobs added to queue. Ensure Horizon is running!');

        Artisan::call('cache:clear');
        $this->command->outputComponents()->info('Cache cleared');

        $this->command->outputComponents()->success('Database seeding complete');
    }
}
