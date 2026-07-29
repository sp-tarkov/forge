<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\License;
use App\Models\Mod;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AddonSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();

        $mods = Mod::query()->get(['id', 'owner_id']);
        /** @var list<int> $userIds */
        $userIds = User::query()->pluck('id')->all();
        /** @var list<int> $licenseIds */
        $licenseIds = License::query()->pluck('id')->all();

        // Select 75% of mods to have addons
        $modsWithAddons = $mods->shuffle()->take((int) ceil($mods->count() * 0.75))->values();

        $addonIds = $this->seedAddons($modsWithAddons, $licenseIds);
        $this->seedSourceCodeLinks($addonIds);
        $addonVersionIds = $this->seedAddonVersions($addonIds);
        $this->seedVirusTotalLinks($addonVersionIds);
        $this->attachAdditionalAuthors($addonIds, $userIds);
        $this->calculateAddonDownloads();
    }

    /**
     * Bulk-create 1-3 addons per mod and return the new addon IDs.
     *
     * @param  Collection<int, Mod>  $mods
     * @param  list<int>  $licenseIds
     * @return list<int>
     */
    private function seedAddons(Collection $mods, array $licenseIds): array
    {
        $rows = [];
        foreach ($mods as $mod) {
            $addonCount = random_int(1, 3);
            for ($i = 0; $i < $addonCount; $i++) {
                $name = Str::title(mb_rtrim($this->faker->sentence(random_int(2, 4)), '.'));
                $containsAiContent = $this->faker->boolean();
                $isDetached = random_int(1, 100) <= 5;
                $createdAt = $this->randomPastDate(365);

                $rows[] = [
                    'mod_id' => $mod->id,
                    'owner_id' => $mod->owner_id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'teaser' => $this->faker->sentence(),
                    'description' => $this->faker->paragraphs(random_int(2, 5), true),
                    'license_id' => $licenseIds !== [] ? $this->randomElement($licenseIds) : null,
                    'downloads' => 0,
                    'disabled' => random_int(1, 100) <= 20,
                    'contains_ai_content' => $containsAiContent,
                    'custom_ai_disclosure' => $containsAiContent ? $this->faker->sentence() : null,
                    'contains_ads' => $this->faker->boolean(),
                    'comments_disabled' => random_int(1, 100) <= 10,
                    'detached_at' => $isDetached ? Date::now()->subDays(random_int(0, 30)) : null,
                    'detached_by_user_id' => $isDetached ? $mod->owner_id : null,
                    'discord_notification_sent' => true,
                    'published_at' => $this->randomPastDate(365),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        return $this->bulkInsertReturningIds('addons', $rows);
    }

    /**
     * Bulk-create 0-2 source code links per addon.
     *
     * @param  list<int>  $addonIds
     */
    private function seedSourceCodeLinks(array $addonIds): void
    {
        $now = Date::now();

        $rows = [];
        foreach ($addonIds as $addonId) {
            $linkCount = random_int(0, 2);
            for ($i = 0; $i < $linkCount; $i++) {
                $provider = $this->randomElement(['github.com', 'gitlab.com', 'bitbucket.org']);

                $rows[] = [
                    'sourceable_type' => Addon::class,
                    'sourceable_id' => $addonId,
                    'url' => sprintf('https://%s/%s/%s', $provider, $this->faker->userName(), $this->faker->slug()),
                    'label' => random_int(1, 10) <= 7 ? $this->randomElement(['GitHub', 'GitLab', 'Mirror', 'Documentation']) : '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->bulkInsert('source_code_links', $rows);
    }

    /**
     * Bulk-create 1-5 versions per addon and return the new version IDs.
     *
     * @param  list<int>  $addonIds
     * @return list<int>
     */
    private function seedAddonVersions(array $addonIds): array
    {
        $rows = [];
        foreach ($addonIds as $addonId) {
            $versionCount = random_int(1, 5);
            for ($i = 0; $i < $versionCount; $i++) {
                $major = random_int(0, 9);
                $minor = random_int(0, 9);
                $patch = random_int(0, 9);
                $createdAt = $this->randomPastDate(365);

                $rows[] = [
                    'addon_id' => $addonId,
                    'version' => sprintf('%d.%d.%d', $major, $minor, $patch),
                    'version_major' => $major,
                    'version_minor' => $minor,
                    'version_patch' => $patch,
                    'version_pre_release' => '',
                    'description' => $this->faker->text(),
                    'link' => 'https://example.com/'.$this->faker->slug().'.7z',
                    'mod_version_constraint' => $this->randomElement(['^1.0.0', '^2.0.0', '>=3.0.0', '<4.0.0']),
                    'content_length' => random_int(1000, 10000000),
                    'downloads' => $this->faker->randomNumber(),
                    'disabled' => random_int(1, 100) <= 10,
                    'discord_notification_sent' => true,
                    'published_at' => $this->randomPastDate(365),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        return $this->bulkInsertReturningIds('addon_versions', $rows);
    }

    /**
     * Bulk-create one virus total link per addon version.
     *
     * @param  list<int>  $addonVersionIds
     */
    private function seedVirusTotalLinks(array $addonVersionIds): void
    {
        $now = Date::now();

        $rows = [];
        foreach ($addonVersionIds as $addonVersionId) {
            $rows[] = $this->virusTotalLinkRow(AddonVersion::class, $addonVersionId, $now);
        }

        $this->bulkInsert('virus_total_links', $rows);
    }

    /**
     * Bulk-attach 1-2 additional authors to roughly 30% of the addons.
     *
     * @param  list<int>  $addonIds
     * @param  list<int>  $userIds
     */
    private function attachAdditionalAuthors(array $addonIds, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $now = Date::now();

        $rows = [];
        foreach ($addonIds as $addonId) {
            if (random_int(0, 9) >= 3) {
                continue;
            }

            foreach ($this->randomElements($userIds, random_int(1, 2)) as $userId) {
                $rows[] = [
                    'authorable_type' => Addon::class,
                    'authorable_id' => $addonId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        $this->bulkInsert('additional_authors', $rows);
    }

    /**
     * Set every addon's download count to the sum of its version download counts.
     */
    private function calculateAddonDownloads(): void
    {
        DB::update(<<<'SQL'
            UPDATE addons
            SET downloads = COALESCE((
                SELECT SUM(addon_versions.downloads)
                FROM addon_versions
                WHERE addon_versions.addon_id = addons.id
            ), 0)
            SQL);
    }
}
