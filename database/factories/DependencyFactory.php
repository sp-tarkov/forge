<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dependency;
use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Dependency>
 */
class DependencyFactory extends Factory
{
    protected $model = Dependency::class;

    public function definition(): array
    {
        return [
            'dependable_type' => ModVersion::class,
            'dependable_id' => ModVersion::factory(),
            'dependent_mod_id' => Mod::factory(),
            'constraint' => '*',
            'created_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
            'updated_at' => Carbon::now()->subDays(rand(0, 365))->subHours(rand(0, 23)),
        ];
    }

    /**
     * Configure the dependency to belong to a mod version.
     */
    public function forModVersion(ModVersion $modVersion): static
    {
        return $this->state([
            'dependable_type' => ModVersion::class,
            'dependable_id' => $modVersion->id,
        ]);
    }
}
