<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

final class ModCategorySeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Weapons' => 'Weapon mods for SPT',
            'Items' => 'Item mods for SPT',
            'Maps' => 'Map mods for SPT',
            'Traders' => 'Trader mods for SPT',
            'AI & Bots' => 'AI and bot behavior mods',
            'User Interface' => 'UI and HUD mods',
            'Gameplay' => 'Gameplay modification mods',
            'Tools & Utilities' => 'Tools and utility mods',
            'Assault Rifles' => 'Assault rifle weapon mods',
            'Pistols' => 'Pistol weapon mods',
            'Sniper Rifles' => 'Sniper rifle weapon mods',
            'Armor' => 'Armor item mods',
            'Medical' => 'Medical item mods',
            'Difficulty' => 'Difficulty adjustment mods',
            'Economy' => 'Economy and loot mods',
            'Shotguns' => 'Shotgun weapon mods',
            'SMGs' => 'Submachine gun weapon mods',
            'Consumables' => 'Consumable item mods',
            'Quests' => 'Quest and mission mods',
            'Audio' => 'Audio and sound mods',
        ];

        $now = Date::now();

        $rows = [];
        foreach ($categories as $title => $description) {
            $rows[] = [
                'title' => $title,
                'slug' => Str::slug($title),
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkInsert('mod_categories', $rows);
    }
}
