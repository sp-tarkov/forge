<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mod;
use App\Models\ModSourceCodeLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModSourceCodeLink>
 */
class ModSourceCodeLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ModSourceCodeLink>
     */
    protected $model = ModSourceCodeLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $providers = ['github.com', 'gitlab.com', 'bitbucket.org'];
        $provider = $this->faker->randomElement($providers);

        return [
            'mod_id' => Mod::factory(),
            'url' => sprintf('https://%s/%s/%s', $provider, $this->faker->userName(), $this->faker->slug()),
            'label' => $this->faker->optional(0.7, '')->randomElement(['GitHub', 'GitLab', 'Mirror', 'Documentation']),
        ];
    }
}
