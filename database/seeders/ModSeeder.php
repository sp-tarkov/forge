<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FikaCompatibility;
use App\Models\License;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class ModSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();
        $counts = $this->getDefaultCounts();

        /** @var int $licenseCount */
        $licenseCount = $counts['license'];
        /** @var int $modCount */
        $modCount = $counts['mod'];
        /** @var int $modVersionCount */
        $modVersionCount = $counts['modVersion'];

        /** @var non-empty-list<int> $licenseIds */
        $licenseIds = License::factory($licenseCount)->create()->pluck('id')->all();
        /** @var list<int> $categoryIds */
        $categoryIds = ModCategory::query()->pluck('id')->all();
        /** @var list<int> $userIds */
        $userIds = User::query()->pluck('id')->all();

        $ownerIds = $this->seedModOwners($modCount);
        $modIds = $this->seedMods($modCount, $licenseIds, $categoryIds, $ownerIds);
        $this->seedSourceCodeLinks($modIds);
        $this->attachAdditionalAuthors($modIds, $userIds);
        $modVersionIds = $this->seedModVersions($modVersionCount, $modIds);
        $this->seedVirusTotalLinks($modVersionIds);
        $this->seedModDependencies($modVersionIds, $modIds);
        $this->seedMarkdownTestMod();
    }

    /**
     * Bulk-create one owner user per mod and return the new user IDs.
     *
     * @return list<int>
     */
    private function seedModOwners(int $modCount): array
    {
        /** @var list<string> $existingNames */
        $existingNames = User::query()->pluck('name')->all();
        /** @var list<string> $existingEmails */
        $existingEmails = User::query()->pluck('email')->all();

        $takenNames = array_fill_keys($existingNames, true);
        $takenEmails = array_fill_keys($existingEmails, true);

        $passwordHash = Hash::make('password');
        $now = Date::now();

        $rows = [];
        for ($i = 0; $i < $modCount; $i++) {
            $rows[] = $this->buildUserRow($takenNames, $takenEmails, $passwordHash, $now);
        }

        return $this->bulkInsertReturningIds('users', $rows);
    }

    /**
     * Bulk-create mods and return the new mod IDs.
     *
     * @param  non-empty-list<int>  $licenseIds
     * @param  list<int>  $categoryIds
     * @param  list<int>  $ownerIds
     * @return list<int>
     */
    private function seedMods(int $modCount, array $licenseIds, array $categoryIds, array $ownerIds): array
    {
        $rows = [];
        for ($i = 0; $i < $modCount; $i++) {
            $name = Str::title(mb_rtrim($this->faker->sentence(random_int(3, 5)), '.'));
            $slug = Str::slug($name);
            $containsAiContent = $this->faker->boolean();
            $createdAt = $this->randomPastDate(365);

            $rows[] = [
                'owner_id' => $ownerIds[$i],
                'name' => $name,
                'slug' => $slug,
                'guid' => 'com.'.$this->faker->domainWord().'.'.$slug.'.'.Str::lower(Str::random(6)),
                'teaser' => $this->faker->sentence(),
                'description' => $this->faker->paragraphs(random_int(4, 20), true),
                'license_id' => $this->randomElement($licenseIds),
                'category_id' => $categoryIds !== [] && random_int(1, 10) <= 8 ? $this->randomElement($categoryIds) : null,
                'featured' => $this->faker->boolean(),
                'contains_ai_content' => $containsAiContent,
                'custom_ai_disclosure' => $containsAiContent ? $this->faker->sentence() : null,
                'contains_ads' => $this->faker->boolean(),
                'discord_notification_sent' => true,
                'published_at' => $this->randomPastDate(365),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        return $this->bulkInsertReturningIds('mods', $rows);
    }

    /**
     * Bulk-create 1-3 source code links per mod.
     *
     * @param  list<int>  $modIds
     */
    private function seedSourceCodeLinks(array $modIds): void
    {
        $now = Date::now();

        $rows = [];
        foreach ($modIds as $modId) {
            $linkCount = random_int(1, 3);
            for ($i = 0; $i < $linkCount; $i++) {
                $provider = $this->randomElement(['github.com', 'gitlab.com', 'bitbucket.org']);

                $rows[] = [
                    'sourceable_type' => Mod::class,
                    'sourceable_id' => $modId,
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
     * Bulk-attach 0-2 additional authors to each mod.
     *
     * @param  list<int>  $modIds
     * @param  list<int>  $userIds
     */
    private function attachAdditionalAuthors(array $modIds, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $rows = [];
        foreach ($modIds as $modId) {
            $authorCount = random_int(0, 2);
            if ($authorCount === 0) {
                continue;
            }

            foreach ($this->randomElements($userIds, $authorCount) as $userId) {
                $rows[] = [
                    'authorable_type' => Mod::class,
                    'authorable_id' => $modId,
                    'user_id' => $userId,
                    'created_at' => null,
                    'updated_at' => null,
                ];
            }
        }

        $this->bulkInsert('additional_authors', $rows);
    }

    /**
     * Bulk-create mod versions spread randomly across the mods and return the new version IDs.
     *
     * @param  list<int>  $modIds
     * @return list<int>
     */
    private function seedModVersions(int $modVersionCount, array $modIds): array
    {
        if ($modIds === []) {
            return [];
        }

        $rows = [];
        for ($i = 0; $i < $modVersionCount; $i++) {
            $major = random_int(0, 9);
            $minor = random_int(0, 9);
            $patch = random_int(0, 9);
            $createdAt = $this->randomPastDate(365);

            $rows[] = [
                'mod_id' => $this->randomElement($modIds),
                'version' => sprintf('%d.%d.%d', $major, $minor, $patch),
                'version_major' => $major,
                'version_minor' => $minor,
                'version_patch' => $patch,
                'version_labels' => '',
                'description' => $this->faker->text(),
                'link' => 'https://example.com/'.$this->faker->slug().'.7z',
                'spt_version_constraint' => $this->randomElement(['^1.0.0', '^2.0.0', '>=3.0.0', '<4.0.0']),
                'downloads' => $this->faker->randomNumber(),
                'disabled' => false,
                'fika_compatibility' => $this->randomElement(FikaCompatibility::cases()),
                'discord_notification_sent' => true,
                'published_at' => $this->randomPastDate(365),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        return $this->bulkInsertReturningIds('mod_versions', $rows);
    }

    /**
     * Bulk-create one virus total link per mod version.
     *
     * @param  list<int>  $modVersionIds
     */
    private function seedVirusTotalLinks(array $modVersionIds): void
    {
        $now = Date::now();

        $rows = [];
        foreach ($modVersionIds as $modVersionId) {
            $rows[] = $this->virusTotalLinkRow(ModVersion::class, $modVersionId, $now);
        }

        $this->bulkInsert('virus_total_links', $rows);
    }

    /**
     * Bulk-create 1-3 dependencies for roughly 30% of the mod versions.
     *
     * @param  list<int>  $modVersionIds
     * @param  list<int>  $modIds
     */
    private function seedModDependencies(array $modVersionIds, array $modIds): void
    {
        if ($modIds === []) {
            return;
        }

        $rows = [];
        foreach ($modVersionIds as $modVersionId) {
            // 70% chance has no dependencies
            if (random_int(0, 9) >= 3) {
                continue;
            }

            $createdAt = $this->randomPastDate(365);
            foreach ($this->randomElements($modIds, random_int(1, 3)) as $dependencyModId) {
                $rows[] = [
                    'dependable_type' => ModVersion::class,
                    'dependable_id' => $modVersionId,
                    'dependent_mod_id' => $dependencyModId,
                    'constraint' => '*',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        $this->bulkInsert('dependencies', $rows);
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
}
