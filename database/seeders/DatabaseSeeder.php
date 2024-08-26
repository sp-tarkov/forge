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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
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

        $progress = $this->command->getOutput()->createProgressBar(5);
        $progress->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%");
        $progress->setMessage("starting ...");

        $progress->start();

        $progress->setMessage('adding users ...');
        User::factory(4)->for($administrator, 'role')->create();

        // Add 10 moderators.
        $moderator = UserRole::factory()->moderator()->create();
        User::factory(5)->for($moderator, 'role')->create();

        // Add 100 users.
        $users = User::factory(100)->create();
        $progress->advance();



        // Add 300 mods, assigning them to the users we just created.
        $progress->setMessage('adding mods ...');
        $allUsers = $users->merge([$administrator, $moderator]);
        $mods = Mod::factory(300)->recycle([$licenses])->create();
        foreach ($mods as $mod) {
            $userIds = $allUsers->random(rand(1, 3))->pluck('id')->toArray();
            $mod->users()->attach($userIds);
        }
        $progress->advance();

        // Add 3000 mod versions, assigning them to the mods we just created.
        $progress->setMessage('adding mod versions ...');
        $modVersions = ModVersion::factory(3000)->recycle([$mods, $spt_versions])->create();
        $progress->advance();

        // Add ModDependencies to a subset of ModVersions.
        $progress->setMessage('adding mod dependencies ...');
        foreach ($modVersions as $modVersion) {
            $hasDependencies = rand(0, 100) < 30; // 30% chance to have dependencies
            if ($hasDependencies) {
                $dependencyMods = $mods->random(rand(1, 3)); // 1 to 3 dependencies
                foreach ($dependencyMods as $dependencyMod) {
                    ModDependency::factory()->recycle([$modVersion, $dependencyMod])->create();
                }
            }
        }
        $progress->advance();
        $progress->finish();
        $this->command->info('');
        $this->command->outputComponents()->success('Database seeded');
    }
}
