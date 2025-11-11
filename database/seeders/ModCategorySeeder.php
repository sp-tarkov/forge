<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ModCategory;
use Illuminate\Database\Seeder;

class ModCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create flat categories (formerly root and child categories merged)
        ModCategory::factory()->create([
            'title' => 'Weapons',
            'description' => 'Weapon mods for SPT',
        ]);

        ModCategory::factory()->create([
            'title' => 'Items',
            'description' => 'Item mods for SPT',
        ]);

        ModCategory::factory()->create([
            'title' => 'Maps',
            'description' => 'Map mods for SPT',
        ]);

        ModCategory::factory()->create([
            'title' => 'Traders',
            'description' => 'Trader mods for SPT',
        ]);

        ModCategory::factory()->create([
            'title' => 'AI & Bots',
            'description' => 'AI and bot behavior mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'User Interface',
            'description' => 'UI and HUD mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Gameplay',
            'description' => 'Gameplay modification mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Tools & Utilities',
            'description' => 'Tools and utility mods',
        ]);

        // Former subcategories, now flat categories
        ModCategory::factory()->create([
            'title' => 'Assault Rifles',
            'description' => 'Assault rifle weapon mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Pistols',
            'description' => 'Pistol weapon mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Sniper Rifles',
            'description' => 'Sniper rifle weapon mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Armor',
            'description' => 'Armor item mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Medical',
            'description' => 'Medical item mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Difficulty',
            'description' => 'Difficulty adjustment mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Economy',
            'description' => 'Economy and loot mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Shotguns',
            'description' => 'Shotgun weapon mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'SMGs',
            'description' => 'Submachine gun weapon mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Consumables',
            'description' => 'Consumable item mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Quests',
            'description' => 'Quest and mission mods',
        ]);

        ModCategory::factory()->create([
            'title' => 'Audio',
            'description' => 'Audio and sound mods',
        ]);

        $this->command->outputComponents()->info('Mod categories seeded');
    }
}
