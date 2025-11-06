<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mod;
use App\Models\SourceCodeLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SourceCodeLink>
 */
class SourceCodeLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SourceCodeLink>
     */
    protected $model = SourceCodeLink::class;

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
            'sourceable_type' => Mod::class,
            'sourceable_id' => Mod::factory(),
            'url' => sprintf('https://%s/%s/%s', $provider, $this->faker->userName(), $this->faker->slug()),
            'label' => $this->faker->optional(0.7, '')->randomElement(['GitHub', 'GitLab', 'Mirror', 'Documentation']),
        ];
    }
}
