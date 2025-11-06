<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SptVersion;
use Illuminate\Database\Seeder;

class SptVersionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedSptVersions();
    }

    /**
     * Seed SPT versions that match the constraints used in ModVersionFactory.
     */
    private function seedSptVersions(): void
    {
        // '^1.0.0', '^2.0.0', '>=3.0.0', '<4.0.0'
        $versions = [
            '1.0.0', '1.0.1', '1.0.2', '1.0.3',
            '1.1.0', '1.1.1', '1.1.2',
            '1.2.0', '1.2.1',
            '1.3.0',
            '2.0.0', '2.0.1', '2.0.2',
            '2.1.0', '2.1.1',
            '2.2.0',
            '2.3.0',
            '3.0.0', '3.0.1', '3.0.2', '3.0.3',
            '3.1.0', '3.1.1',
            '3.2.0', '3.2.1',
            '3.3.0',
            '3.4.0',
            '3.5.0',
            '4.0.0', '4.0.1', '4.0.2',
            '4.1.0', '4.1.1',
            '4.2.0',
            '4.3.0',
            '5.0.0', '5.0.1', '5.0.2',
            '5.1.0', '5.1.1',
            '5.2.0',
            '5.3.0',
        ];

        $colorClasses = ['cyan', 'lime', 'green', 'yellow', 'red', 'orange'];

        foreach ($versions as $version) {
            $parts = explode('.', $version);
            $major = (int) $parts[0];
            $minor = (int) $parts[1];
            $patch = (int) $parts[2];

            // Use different colors for different major versions
            $colorClass = $colorClasses[$major % count($colorClasses)];

            SptVersion::firstOrCreate([
                'version' => $version,
            ], [
                'version_major' => $major,
                'version_minor' => $minor,
                'version_patch' => $patch,
                'version_labels' => '',
                'mod_count' => 0,
                'link' => "https://github.com/sp-tarkov/build/releases/tag/{$version}",
                'color_class' => $colorClass,
                'publish_date' => now()->subDays(rand(30, 365)),
            ]);
        }

        $count = SptVersion::count();
        $this->command->outputComponents()->success("Created {$count} SPT versions");
    }
}
