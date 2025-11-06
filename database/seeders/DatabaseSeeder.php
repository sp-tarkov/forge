<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        ini_set('memory_limit', '512M');

        $this->call([
            SptVersionSeeder::class,
            UserSeeder::class,
            ModCategorySeeder::class,
            ModSeeder::class,
            AddonSeeder::class,
            CommentSeeder::class,
            TrackingEventSeeder::class,
            ConversationSeeder::class,
        ]);

        Artisan::call('app:search-sync');
        Artisan::call('app:resolve-versions');
        Artisan::call('app:count-mods');
        Artisan::call('app:update-downloads');

        Artisan::call('cache:clear');
    }
}
