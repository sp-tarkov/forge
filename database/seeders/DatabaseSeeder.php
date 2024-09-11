<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\progress;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // how many of each "thing" to make during seeding
        $userCount = 100;
        $modCount = 300;
        $modVersionCount = 3000;

        // Create a few SPT versions.
        $spt_versions = SptVersion::factory(30)->create();

        // Create some code licenses.
        $licenses = License::factory(10)->create();

        // Add administrators.
        $administratorRole = UserRole::factory()->administrator()->create();
        $testAccount = User::factory()->for($administratorRole, 'role')->create([
            'email' => 'test@example.com',
        ]);

        $this->command->outputComponents()->info("test account created: $testAccount->email");

        User::factory(4)->for($administratorRole, 'role')->create();

        // Add moderators.
        $moderatorRole = UserRole::factory()->moderator()->create();
        User::factory(5)->for($moderatorRole, 'role')->create();

        // Add users
        progress(
            label: 'adding users...',
            steps: $userCount,
            callback: fn () => User::factory()->create()
        );

        // get all users
        $allUsers = User::all();

        // Add user follows
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

        $mods = collect(progress(
            label: 'adding mods...',
            steps: $modCount,
            callback: fn () => Mod::factory()->recycle([$licenses])->create()
        ));

        // attach users to mods
        progress(
            label: 'attaching mod users ...',
            steps: $mods,
            callback: function ($mod) use ($allUsers) {
                $userIds = $allUsers->random(rand(1, 3))->pluck('id')->toArray();
                $mod->users()->attach($userIds);
            }
        );

        // Add mod versions, assigning them to the mods we just created.
        $modVersions = collect(progress(
            label: 'adding mods versions ...',
            steps: $modVersionCount,
            callback: fn () => ModVersion::factory()->recycle([$mods, $spt_versions])->create()
        ));

        // Add ModDependencies to a subset of ModVersions.
        progress(
            label: 'adding mods dependencies ...',
            steps: $modVersions,
            callback: function ($modVersion) use ($mods) {
                $hasDependencies = rand(0, 100) < 30; // 30% chance to have dependencies
                if ($hasDependencies) {
                    $dependencyMods = $mods->random(rand(1, 3)); // 1 to 3 dependencies
                    foreach ($dependencyMods as $dependencyMod) {
                        ModDependency::factory()->recycle([$modVersion, $dependencyMod])->create();
                    }
                }
            }
        );

        $this->command->outputComponents()->success('Database seeded');
    }
}
