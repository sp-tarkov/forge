<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a few SPT versions.
        $spt_versions = SptVersion::factory(10)->create();

        // Create some code licenses.
        $licenses = License::factory(10)->create();

        // Add 5 administrators.
        $administrator = UserRole::factory()->administrator()->create();
        User::factory()->for($administrator, 'role')->create([
            'email' => 'test@example.com',
        ]);
        User::factory(4)->for($administrator, 'role')->create();

        // Add 10 moderators.
        $moderator = UserRole::factory()->moderator()->create();
        User::factory(5)->for($moderator, 'role')->create();

        // Add 100 users.
        $users = User::factory(100)->create();

        // Add 200 mods, assigning them to the users we just created.
        $allUsers = $users->merge([$administrator, $moderator]);
        $mods = Mod::factory(200)->recycle([$licenses])->create();
        foreach ($mods as $mod) {
            $userIds = $allUsers->random(rand(1, 3))->pluck('id')->toArray();
            $mod->users()->attach($userIds);
        }

        // Add 1000 mod versions, assigning them to the mods we just created.
        ModVersion::factory(1000)->recycle([$mods, $spt_versions])->create();
    }
}
