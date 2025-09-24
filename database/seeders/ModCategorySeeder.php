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
        // Create root categories
        $weapons = ModCategory::factory()->create([
            'title' => 'Weapons',
            'description' => 'Weapon mods for SPT',
            'show_order' => 10,
        ]);

        $items = ModCategory::factory()->create([
            'title' => 'Items',
            'description' => 'Item mods for SPT',
            'show_order' => 20,
        ]);

        $maps = ModCategory::factory()->create([
            'title' => 'Maps',
            'description' => 'Map mods for SPT',
            'show_order' => 30,
        ]);

        $traders = ModCategory::factory()->create([
            'title' => 'Traders',
            'description' => 'Trader mods for SPT',
            'show_order' => 40,
        ]);

        $ai = ModCategory::factory()->create([
            'title' => 'AI & Bots',
            'description' => 'AI and bot behavior mods',
            'show_order' => 50,
        ]);

        $ui = ModCategory::factory()->create([
            'title' => 'User Interface',
            'description' => 'UI and HUD mods',
            'show_order' => 60,
        ]);

        $gameplay = ModCategory::factory()->create([
            'title' => 'Gameplay',
            'description' => 'Gameplay modification mods',
            'show_order' => 70,
        ]);

        $tools = ModCategory::factory()->create([
            'title' => 'Tools & Utilities',
            'description' => 'Tools and utility mods',
            'show_order' => 80,
        ]);

        // Create some subcategories
        ModCategory::factory()->create([
            'parent_category_id' => $weapons->id,
            'title' => 'Assault Rifles',
            'description' => 'Assault rifle weapon mods',
            'show_order' => 10,
        ]);

        ModCategory::factory()->create([
            'parent_category_id' => $weapons->id,
            'title' => 'Pistols',
            'description' => 'Pistol weapon mods',
            'show_order' => 20,
        ]);

        ModCategory::factory()->create([
            'parent_category_id' => $weapons->id,
            'title' => 'Sniper Rifles',
            'description' => 'Sniper rifle weapon mods',
            'show_order' => 30,
        ]);

        ModCategory::factory()->create([
            'parent_category_id' => $items->id,
            'title' => 'Armor',
            'description' => 'Armor item mods',
            'show_order' => 10,
        ]);

        ModCategory::factory()->create([
            'parent_category_id' => $items->id,
            'title' => 'Medical',
            'description' => 'Medical item mods',
            'show_order' => 20,
        ]);

        ModCategory::factory()->create([
            'parent_category_id' => $gameplay->id,
            'title' => 'Difficulty',
            'description' => 'Difficulty adjustment mods',
            'show_order' => 10,
        ]);

        ModCategory::factory()->create([
            'parent_category_id' => $gameplay->id,
            'title' => 'Economy',
            'description' => 'Economy and loot mods',
            'show_order' => 20,
        ]);

        $this->command->outputComponents()->info('Mod categories seeded');
    }
}
