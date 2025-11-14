<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Laravel\Prompts\Progress;

use function Laravel\Prompts\progress;

class AddonSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * @var Collection<int, Addon>
     */
    private Collection $addons;

    /**
     * @var Collection<int, AddonVersion>
     */
    private Collection $addonVersions;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();

        $mods = Mod::all();
        $allUsers = User::all();

        // Select 75% of mods to have addons
        $modsWithAddons = $mods->shuffle()->take((int) ceil($mods->count() * 0.75));

        // Create addons for selected mods
        $this->seedAddons($modsWithAddons, $allUsers);

        // Add addon versions
        $this->seedAddonVersions();

        // Attach users to addons
        $this->attachUsersToAddons($allUsers);

        // Calculate download counts for all addons
        $this->calculateAddonDownloads();
    }

    /**
     * Seed addons for mods.
     *
     * @param  Collection<int, Mod>  $mods
     * @param  Collection<int, User>  $allUsers
     */
    private function seedAddons(Collection $mods, Collection $allUsers): void
    {
        $this->addons = collect();

        Addon::withoutEvents(function () use ($mods, $allUsers) {
            progress(
                label: 'Creating Addons...',
                steps: $mods,
                callback: function (Mod $mod, Progress $progress) use ($allUsers) {
                    // Each mod can have 1-3 addons
                    $addonCount = rand(1, 3);

                    for ($i = 0; $i < $addonCount; $i++) {
                        $addon = Addon::factory()
                            ->recycle([$mod])
                            ->recycle($allUsers)
                            ->create([
                                'owner_id' => $mod->owner_id,
                            ]);

                        // 20% chance for addon to be disabled
                        if (rand(1, 100) <= 20) {
                            $addon->disabled = true;
                            $addon->save();
                        }

                        // Small chance (10%) of having comments disabled
                        if (rand(1, 100) <= 10) {
                            $addon->comments_disabled = true;
                            $addon->save();
                        }

                        // Small chance (5%) of being detached
                        if (rand(1, 100) <= 5) {
                            $addon->detached_at = now()->subDays(rand(0, 30));
                            $addon->detached_by_user_id = $mod->owner_id;
                            $addon->save();
                        }

                        $this->addons->push($addon);
                    }
                }
            );
        });
    }

    /**
     * Seed addon versions.
     */
    private function seedAddonVersions(): void
    {
        $this->addonVersions = collect();

        AddonVersion::withoutEvents(function () {
            progress(
                label: 'Creating Addon Versions...',
                steps: $this->addons,
                callback: function (Addon $addon, Progress $progress) {
                    // Each addon can have 1-5 versions
                    $versionCount = rand(1, 5);

                    for ($i = 0; $i < $versionCount; $i++) {
                        $version = AddonVersion::factory()
                            ->recycle([$addon])
                            ->create();

                        // 10% chance for version to be disabled
                        if (rand(1, 100) <= 10) {
                            $version->disabled = true;
                            $version->save();
                        }

                        $this->addonVersions->push($version);
                    }
                }
            );
        });
    }

    /**
     * Attach users to addons.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function attachUsersToAddons(Collection $allUsers): void
    {
        progress(
            label: 'Attaching authors to addons...',
            steps: $this->addons,
            callback: function (Addon $addon, Progress $progress) use ($allUsers) {
                // 30% chance to have additional authors (beyond the owner)
                if (rand(0, 9) < 3) {
                    $userIds = $allUsers->random(rand(1, 2))->pluck('id')->toArray();
                    if (count($userIds)) {
                        $addon->additionalAuthors()->attach($userIds);
                    }
                }
            }
        );
    }

    /**
     * Calculate download counts for all addons.
     */
    private function calculateAddonDownloads(): void
    {
        progress(
            label: 'Calculating addon download counts...',
            steps: $this->addons,
            callback: function (Addon $addon, Progress $progress) {
                $addon->calculateDownloads();
            }
        );
    }
}
