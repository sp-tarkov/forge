<?php

namespace Database\Factories;

use App\Models\SptVersion;
use App\Support\Version;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SptVersion>
 */
class SptVersionFactory extends Factory
{
    protected $model = SptVersion::class;

    public function definition(): array
    {
        $versionString = $this->faker->numerify('#.#.#');
        try {
            $version = new Version($versionString);
        } catch (\Exception $e) {
            $version = new Version('0.0.0');
        }

        return [
            'version' => $versionString,
            'version_major' => $version->getMajor(),
            'version_minor' => $version->getMinor(),
            'version_patch' => $version->getPatch(),
            'version_pre_release' => $version->getPreRelease(),
            'color_class' => $this->faker->randomElement(['red', 'green', 'emerald', 'lime', 'yellow', 'grey']),
            'link' => $this->faker->url,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
