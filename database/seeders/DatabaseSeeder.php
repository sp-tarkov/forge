<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Increase memory limit for seeding
        ini_set('memory_limit', '512M');

        // Disable Scout search syncing for models that use Searchable trait
        Mod::withoutSyncingToSearch(function () {
            User::withoutSyncingToSearch(function () {
                $this->runSeeding();
            });
        });

        // Run post-seeding commands
        $this->runPostSeedingCommands();

        $this->command->outputComponents()->success('Database seeding complete');
    }

    /**
     * Run the actual seeding logic.
     */
    private function runSeeding(): void
    {
        // Run seeders in order of dependencies
        $this->call([
            SptVersionSeeder::class,
            UserSeeder::class,
            ModSeeder::class,
            CommentSeeder::class,
            TrackingEventSeeder::class,
            ConversationSeeder::class,
        ]);

        $this->command->outputComponents()->success('Initial seeding complete');
    }

    /**
     * Run post-seeding commands.
     */
    private function runPostSeedingCommands(): void
    {
        Artisan::call('app:search-sync');
        Artisan::call('app:resolve-versions');
        Artisan::call('app:count-mods');
        Artisan::call('app:update-downloads');
        $this->command->outputComponents()->info('Jobs added to queue. Ensure queue is running!');

        Artisan::call('cache:clear');
        $this->command->outputComponents()->success('Cache cleared');
    }
}
