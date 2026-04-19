<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ListVisibility;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModList>
 */
final class ModListFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(mb_rtrim(fake()->sentence(random_int(2, 4)), '.'));

        return [
            'owner_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraphs(random_int(1, 3), true),
            'description_html' => null,
            'visibility' => ListVisibility::Public,
            'spt_version_id' => null,
            'share_token' => null,
            'is_default' => false,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => ListVisibility::Public,
            'share_token' => null,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => ListVisibility::Hidden,
            'share_token' => ModList::generateShareToken(),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => ListVisibility::Private,
            'share_token' => null,
        ]);
    }

    public function favourites(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => config()->string('mod-lists.favourites.title', 'Favourites'),
            'slug' => config()->string('mod-lists.favourites.slug', 'favourites'),
            'visibility' => ListVisibility::Private,
            'is_default' => true,
            'share_token' => null,
        ]);
    }
}
