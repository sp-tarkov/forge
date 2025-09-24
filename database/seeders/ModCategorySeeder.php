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
            'show_order' => 10,
        ]);

        ModCategory::factory()->create([
            'title' => 'Items',
            'description' => 'Item mods for SPT',
            'show_order' => 20,
        ]);

        ModCategory::factory()->create([
            'title' => 'Maps',
            'description' => 'Map mods for SPT',
            'show_order' => 30,
        ]);

        ModCategory::factory()->create([
            'title' => 'Traders',
            'description' => 'Trader mods for SPT',
            'show_order' => 40,
        ]);

        ModCategory::factory()->create([
            'title' => 'AI & Bots',
            'description' => 'AI and bot behavior mods',
            'show_order' => 50,
        ]);

        ModCategory::factory()->create([
            'title' => 'User Interface',
            'description' => 'UI and HUD mods',
            'show_order' => 60,
        ]);

        ModCategory::factory()->create([
            'title' => 'Gameplay',
            'description' => 'Gameplay modification mods',
            'show_order' => 70,
        ]);

        ModCategory::factory()->create([
            'title' => 'Tools & Utilities',
            'description' => 'Tools and utility mods',
            'show_order' => 80,
        ]);

        // Former subcategories, now flat categories
        ModCategory::factory()->create([
            'title' => 'Assault Rifles',
            'description' => 'Assault rifle weapon mods',
            'show_order' => 90,
        ]);

        ModCategory::factory()->create([
            'title' => 'Pistols',
            'description' => 'Pistol weapon mods',
            'show_order' => 100,
        ]);

        ModCategory::factory()->create([
            'title' => 'Sniper Rifles',
            'description' => 'Sniper rifle weapon mods',
            'show_order' => 110,
        ]);

        ModCategory::factory()->create([
            'title' => 'Armor',
            'description' => 'Armor item mods',
            'show_order' => 120,
        ]);

        ModCategory::factory()->create([
            'title' => 'Medical',
            'description' => 'Medical item mods',
            'show_order' => 130,
        ]);

        ModCategory::factory()->create([
            'title' => 'Difficulty',
            'description' => 'Difficulty adjustment mods',
            'show_order' => 140,
        ]);

        ModCategory::factory()->create([
            'title' => 'Economy',
            'description' => 'Economy and loot mods',
            'show_order' => 150,
        ]);

        $this->command->outputComponents()->info('Mod categories seeded');
    }
}
