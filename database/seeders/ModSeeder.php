<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\License;
use App\Models\Mod;
use App\Models\ModDependency;
use App\Models\ModVersion;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\progress;

class ModSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * @var Collection<int, Mod>
     */
    private Collection $mods;

    /**
     * @var Collection<int, ModVersion>
     */
    private Collection $modVersions;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();
        $counts = $this->getDefaultCounts();

        $licenses = License::factory($counts['license'])->create();
        $allUsers = User::all();

        // Create mods
        $this->seedMods($counts, $licenses);

        // Attach users to mods
        $this->attachUsersToMods($allUsers);

        // Add mod versions
        $this->seedModVersions($counts);

        // Add mod dependencies
        $this->seedModDependencies();

        // Create markdown test mod
        $this->seedMarkdownTestMod();
    }

    /**
     * Seed mods.
     *
     * @param  array<string, mixed>  $counts
     * @param  Collection<int, License>  $licenses
     */
    private function seedMods(array $counts, Collection $licenses): void
    {
        $this->mods = collect();

        Mod::withoutEvents(function () use ($counts, $licenses) {
            $this->mods = collect(progress(
                label: 'Adding Mods...',
                steps: $counts['mod'],
                callback: fn () => Mod::factory()->recycle([$licenses])->create()
            ));
        });
    }

    /**
     * Attach users to mods.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function attachUsersToMods(Collection $allUsers): void
    {
        progress(
            label: 'Attaching users to mods...',
            steps: $this->mods,
            callback: function (Mod $mod, Progress $progress) use ($allUsers) {
                $userIds = $allUsers->random(rand(0, 2))->pluck('id')->toArray();
                if (count($userIds)) {
                    $mod->authors()->attach($userIds);
                }
            }
        );
    }

    /**
     * Seed mod versions.
     *
     * @param  array<string, mixed>  $counts
     */
    private function seedModVersions(array $counts): void
    {
        $this->modVersions = collect();

        ModVersion::withoutEvents(function () use ($counts) {
            $this->modVersions = collect(progress(
                label: 'Adding Mod Versions...',
                steps: $counts['modVersion'],
                callback: fn () => ModVersion::factory()->recycle([$this->mods])->create()
            ));
        });
    }

    /**
     * Seed mod dependencies.
     */
    private function seedModDependencies(): void
    {
        ModDependency::withoutEvents(function () {
            progress(
                label: 'Adding Mod Dependencies...',
                steps: $this->modVersions,
                callback: function (ModVersion $modVersion, Progress $progress) {
                    // 70% chance has no dependencies
                    if (rand(0, 9) >= 3) {
                        return;
                    }

                    // Choose 1-3 random mods to be dependencies.
                    $dependencyMods = $this->mods->random(rand(1, 3));
                    foreach ($dependencyMods as $dependencyMod) {
                        ModDependency::factory()->recycle([$modVersion, $dependencyMod])->create();
                    }
                }
            );
        });
    }

    /**
     * Create a markdown test mod.
     */
    private function seedMarkdownTestMod(): void
    {
        $markdownPath = database_path('../resources/markdown/exampleModDescription.md');

        if (file_exists($markdownPath)) {
            Mod::factory()->hasVersions(3)->create([
                'name' => 'Markdown Test',
                'slug' => 'markdown-test',
                'description' => file_get_contents($markdownPath),
            ]);
        }
    }

    /**
     * Get the created mods.
     *
     * @return Collection<int, Mod>
     */
    public function getMods(): Collection
    {
        return $this->mods;
    }

    /**
     * Get the created mod versions.
     *
     * @return Collection<int, ModVersion>
     */
    public function getModVersions(): Collection
    {
        return $this->modVersions;
    }
}
