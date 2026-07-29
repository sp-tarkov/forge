<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ListVisibility;
use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\Traits\SeederHelpers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

final class CommentSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Sample non-English comment bodies paired with their English translations, keyed by ISO 639-1 language code.
     *
     * @var array<string, non-empty-list<array{string, string}>>
     */
    private const array TRANSLATED_SAMPLES = [
        'ru' => [
            ['Отличный мод, спасибо за вашу работу! Всё работает без проблем.', 'Great mod, thank you for your work! Everything runs without any problems.'],
            ['Подскажите, пожалуйста, как установить этот мод на последнюю версию игры?', 'Could you please tell me how to install this mod on the latest version of the game?'],
            ['После обновления игра вылетает при загрузке рейда. Помогите, пожалуйста!', 'After the update the game crashes when loading a raid. Please help!'],
        ],
        'de' => [
            ['Der Mod funktioniert bei mir einwandfrei. Vielen Dank für die tolle Arbeit!', 'The mod works flawlessly for me. Many thanks for the great work!'],
            ['Danke für dieses großartige Update, jetzt läuft wieder alles!', 'Thanks for this great update, everything runs again now!'],
        ],
        'fr' => [
            ['Ce mod est vraiment excellent, merci beaucoup pour votre travail!', 'This mod is really excellent, thank you very much for your work!'],
            ['Le mod ne se charge plus après la dernière mise à jour, une solution?', 'The mod no longer loads after the latest update, any solution?'],
        ],
        'zh' => [
            ['这个模组太棒了，感谢你的辛勤工作！', 'This mod is amazing, thank you for your hard work!'],
            ['请问这个模组支持最新版本吗？', 'Does this mod support the latest version?'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();

        /** @var list<int> $userIds */
        $userIds = User::query()->pluck('id')->all();
        $commentables = $this->collectCommentables();

        if ($userIds === [] || $commentables === []) {
            return;
        }

        [$parentRows, $parentVersions] = $this->buildParentComments($commentables, $userIds);
        $parentIds = $this->bulkInsertReturningIds('comments', $parentRows);

        [$replyRows, $replyVersions, $replyRootIds] = $this->buildReplies($parentRows, $parentIds, $userIds);
        $replyIds = $this->bulkInsertReturningIds('comments', $replyRows);

        [$nestedRows, $nestedVersions] = $this->buildNestedReplies($replyRows, $replyIds, $replyRootIds, $userIds);
        $nestedIds = $this->bulkInsertReturningIds('comments', $nestedRows);

        $this->seedCommentVersions([
            [$parentIds, $parentVersions],
            [$replyIds, $replyVersions],
            [$nestedIds, $nestedVersions],
        ]);

        $this->seedCommentReactions([...$parentIds, ...$replyIds, ...$nestedIds], $userIds);
    }

    /**
     * Gather commentable targets with their maximum parent comment counts.
     *
     * @return list<array{class-string, int, int}>
     */
    private function collectCommentables(): array
    {
        /** @var list<int> $modIds */
        $modIds = Mod::query()->pluck('id')->all();
        /** @var list<int> $addonIds */
        $addonIds = Addon::query()->where('comments_disabled', false)->pluck('id')->all();
        /** @var list<int> $listIds */
        $listIds = ModList::query()
            ->where('is_default', false)
            ->where('comments_disabled', false)
            ->whereIn('visibility', [ListVisibility::Public, ListVisibility::Hidden])
            ->pluck('id')
            ->all();

        $targets = [];
        foreach ($modIds as $modId) {
            $targets[] = [Mod::class, $modId, 20];
        }

        foreach ($addonIds as $addonId) {
            $targets[] = [Addon::class, $addonId, 15];
        }

        foreach ($listIds as $listId) {
            $targets[] = [ModList::class, $listId, 10];
        }

        return $targets;
    }

    /**
     * Build 1-N parent comment rows for each commentable target.
     *
     * @param  list<array{class-string, int, int}>  $commentables
     * @param  non-empty-list<int>  $userIds
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function buildParentComments(array $commentables, array $userIds): array
    {
        $rows = [];
        $versions = [];

        foreach ($commentables as [$commentableType, $commentableId, $maxParents]) {
            $parentCommentCount = random_int(1, $maxParents);
            for ($i = 0; $i < $parentCommentCount; $i++) {
                [$row, $version] = $this->buildComment($commentableType, $commentableId, $userIds, null, null, 10, 30);
                $rows[] = $row;
                $versions[] = $version;
            }
        }

        return [$rows, $versions];
    }

    /**
     * Build 1-4 first-level reply rows for roughly 30% of the parent comments.
     *
     * @param  list<array<string, mixed>>  $parentRows
     * @param  list<int>  $parentIds
     * @param  non-empty-list<int>  $userIds
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<int>}
     */
    private function buildReplies(array $parentRows, array $parentIds, array $userIds): array
    {
        $rows = [];
        $versions = [];
        $rootIds = [];

        foreach ($parentIds as $index => $parentId) {
            if (random_int(0, 9) >= 3) {
                continue;
            }

            /** @var class-string $commentableType */
            $commentableType = $parentRows[$index]['commentable_type'];
            /** @var int $commentableId */
            $commentableId = $parentRows[$index]['commentable_id'];

            $replyCount = random_int(1, 4);
            for ($i = 0; $i < $replyCount; $i++) {
                [$row, $version] = $this->buildComment($commentableType, $commentableId, $userIds, $parentId, $parentId, 8, 15);
                $rows[] = $row;
                $versions[] = $version;
                $rootIds[] = $parentId;
            }
        }

        return [$rows, $versions, $rootIds];
    }

    /**
     * Build 1-2 nested reply rows for roughly 40% of the first-level replies.
     *
     * @param  list<array<string, mixed>>  $replyRows
     * @param  list<int>  $replyIds
     * @param  list<int>  $replyRootIds
     * @param  non-empty-list<int>  $userIds
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function buildNestedReplies(array $replyRows, array $replyIds, array $replyRootIds, array $userIds): array
    {
        $rows = [];
        $versions = [];

        foreach ($replyIds as $index => $replyId) {
            if (random_int(0, 9) >= 4) {
                continue;
            }

            /** @var class-string $commentableType */
            $commentableType = $replyRows[$index]['commentable_type'];
            /** @var int $commentableId */
            $commentableId = $replyRows[$index]['commentable_id'];

            $nestedReplyCount = random_int(1, 2);
            for ($i = 0; $i < $nestedReplyCount; $i++) {
                [$row, $version] = $this->buildComment($commentableType, $commentableId, $userIds, $replyId, $replyRootIds[$index], 5, 15);
                $rows[] = $row;
                $versions[] = $version;
            }
        }

        return [$rows, $versions];
    }

    /**
     * Build one comment row and its matching initial version payload.
     *
     * @param  non-empty-list<int>  $userIds
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function buildComment(
        string $commentableType,
        int $commentableId,
        array $userIds,
        ?int $parentId,
        ?int $rootId,
        int $deletionChance,
        int $deletionWindowDays,
    ): array {
        $createdAt = Date::now()->subDays(random_int(0, 30))->subHours(random_int(0, 23));

        $row = [
            'user_id' => $this->randomElement($userIds),
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'parent_id' => $parentId,
            'root_id' => $rootId,
            'spam_status' => $this->getRandomSpamStatus(),
            'spam_metadata' => null,
            'spam_checked_at' => null,
            'spam_recheck_count' => 0,
            'deleted_at' => random_int(0, 100) < $deletionChance ? Date::now()->subDays(random_int(1, $deletionWindowDays)) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];

        return [$row, $this->buildVersionPayload($createdAt)];
    }

    /**
     * Build an initial version payload, roughly ten percent of which are non-English bodies with a stored English
     * translation.
     *
     * @return array<string, mixed>
     */
    private function buildVersionPayload(CarbonInterface $createdAt): array
    {
        if (random_int(0, 99) < 10) {
            $language = array_rand(self::TRANSLATED_SAMPLES);
            [$body, $translatedBody] = $this->randomElement(self::TRANSLATED_SAMPLES[$language]);

            return [
                'body' => $body,
                'version_number' => 1,
                'created_at' => $createdAt,
                'detected_language' => $language,
                'translated_body' => $translatedBody,
                'translation_metadata' => json_encode(['provider' => 'anthropic', 'model' => 'claude-haiku-4-5']),
                'language_detected_at' => $createdAt,
                'translated_at' => $createdAt,
            ];
        }

        return [
            'body' => $this->faker->paragraphs(random_int(1, 3), true),
            'version_number' => 1,
            'created_at' => $createdAt,
            'detected_language' => null,
            'translated_body' => null,
            'translation_metadata' => null,
            'language_detected_at' => null,
            'translated_at' => null,
        ];
    }

    /**
     * Bulk-create the initial version row for every comment.
     *
     * @param  list<array{0: list<int>, 1: list<array<string, mixed>>}>  $waves
     */
    private function seedCommentVersions(array $waves): void
    {
        $rows = [];
        foreach ($waves as [$commentIds, $versions]) {
            foreach ($commentIds as $index => $commentId) {
                $rows[] = ['comment_id' => $commentId] + $versions[$index];
            }
        }

        $this->bulkInsert('comment_versions', $rows, 1000);
    }

    /**
     * Bulk-create 1-5 reactions from distinct users for roughly 40% of the comments.
     *
     * @param  list<int>  $commentIds
     * @param  non-empty-list<int>  $userIds
     */
    private function seedCommentReactions(array $commentIds, array $userIds): void
    {
        $maxReactions = min(5, count($userIds));

        $rows = [];
        foreach ($commentIds as $commentId) {
            if (random_int(0, 9) >= 4) {
                continue;
            }

            $createdAt = Date::now()->subDays(random_int(0, 30))->subHours(random_int(0, 23));
            foreach ($this->randomElements($userIds, random_int(1, $maxReactions)) as $userId) {
                $rows[] = [
                    'user_id' => $userId,
                    'comment_id' => $commentId,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        $this->bulkInsert('comment_reactions', $rows, 1000);
    }
}
