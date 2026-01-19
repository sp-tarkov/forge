<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dependency;
use App\Models\ModVersion;
use App\Models\ResolvedDependency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResolvedDependency>
 */
class ResolvedDependencyFactory extends Factory
{
    protected $model = ResolvedDependency::class;

    public function definition(): array
    {
        return [
            'dependable_type' => ModVersion::class,
            'dependable_id' => ModVersion::factory(),
            'dependency_id' => Dependency::factory(),
            'resolved_mod_version_id' => ModVersion::factory(),
        ];
    }
}
