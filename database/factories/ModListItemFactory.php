<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModListItem>
 */
final class ModListItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mod_list_id' => ModList::factory(),
            'listable_type' => Mod::class,
            'listable_id' => Mod::factory(),
            'note' => null,
            'position' => 0,
            'added_as_dependency' => false,
        ];
    }
}
