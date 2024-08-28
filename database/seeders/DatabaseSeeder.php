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
        $modVersionCount = 500;

        // Create a few SPT versions.
        $spt_versions = SptVersion::factory(30)->create();

        // Create some code licenses.
        $licenses = License::factory(10)->create();

        // Add 5 administrators.
        $administrator = UserRole::factory()->administrator()->create();
        User::factory()->for($administrator, 'role')->create([
            'email' => 'test@example.com',
        ]);

        $this->command->outputComponents()->info('test account created: test@example.com');

        User::factory(4)->for($administrator, 'role')->create();

        // Add 10 moderators.
        $moderator = UserRole::factory()->moderator()->create();
        User::factory(5)->for($moderator, 'role')->create();

        // Add 100 users.
        $users = progress(
            label: 'adding users...',
            steps: $userCount,
            callback: fn () => User::factory()->create()
        );

        //        dd($users);

        $users = collect($users);

        // Add 300 mods, assigning them to the users we just created.
        $allUsers = $users->merge([$administrator, $moderator]);

        $mods = progress(
            label: 'adding mods...',
            steps: $modCount,
            callback: fn () => Mod::factory()->recycle([$licenses])->create()
        );

        $mods = collect($mods);

        progress(
            label: 'attaching mod users ...',
            steps: $mods,
            callback: function ($mod) use ($allUsers) {
                $userIds = $allUsers->random(rand(1, 3))->pluck('id')->toArray();
                $mod->users()->attach($userIds);
            }
        );

        // Add 3000 mod versions, assigning them to the mods we just created.
        $modVersions = progress(
            label: 'adding mods versions ...',
            steps: $modVersionCount,
            callback: fn () => ModVersion::factory()->recycle([$mods, $spt_versions])->create()
        );

        $modVersions = collect($modVersions);

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
