<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ModDependency;
use App\Models\ModResolvedDependency;
use App\Models\ModVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModResolvedDependency>
 */
class ModResolvedDependencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mod_version_id' => ModVersion::factory(),
            'dependency_id' => ModDependency::factory(),
            'resolved_mod_version_id' => ModVersion::factory(),
        ];
    }
}
