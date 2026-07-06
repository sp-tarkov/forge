<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * @extends Factory<Comment>
 */
final class CommentFactory extends Factory
{
    /**
     * Sample non-English comment bodies paired with their English translations, keyed by ISO 639-1 language code.
     *
     * @var array<string, list<array{string, string}>>
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

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commentable_type' => Mod::class,
            'commentable_id' => Mod::factory(),
            'parent_id' => null,
            'spam_status' => SpamStatus::CLEAN,
            'spam_metadata' => null,
            'spam_checked_at' => null,
            'spam_recheck_count' => 0,
            'created_at' => Date::now()->subDays(random_int(0, 30))->subHours(random_int(0, 23)),
            'updated_at' => Date::now()->subDays(random_int(0, 30))->subHours(random_int(0, 23)),
        ];
    }

    /**
     * Create a new model instance.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null): Comment|Collection
    {
        // Handle body attribute by converting to version
        if (is_array($attributes) && array_key_exists('body', $attributes)) {
            $body = $attributes['body'];
            unset($attributes['body']);

            /** @var array<string, mixed> $attributes */
            return $this->withVersion(is_string($body) ? $body : null)->create($attributes, $parent);
        }

        /** @var (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed> $attributes */
        /** @var Comment|Collection<int, Comment> */
        return parent::create($attributes, $parent);
    }

    /**
     * Create a comment with an initial version.
     */
    public function withVersion(?string $body = null): static
    {
        return $this->afterCreating(function (Comment $comment) use ($body): void {
            /** @var string $versionBody */
            $versionBody = $body ?? fake()->paragraphs(random_int(1, 3), true);

            // Validate body length
            /** @var int $maxLength */
            $maxLength = config('comments.validation.max_length', 10000);
            if (mb_strlen($versionBody) > $maxLength) {
                // Delete the comment since version creation failed
                $comment->forceDelete();
                throw new InvalidArgumentException(sprintf('Comment body cannot exceed %d characters.', $maxLength));
            }

            /** @var int $maxVersion */
            $maxVersion = $comment->versions()->max('version_number') ?? 0;

            $comment->versions()->create([
                'body' => $versionBody,
                'version_number' => $maxVersion + 1,
                'created_at' => $comment->created_at,
            ]);
        });
    }

    /**
     * Create a comment whose version was detected as non-English and translated into English. Uses a random sample
     * language when none is given.
     */
    public function translated(?string $language = null): static
    {
        return $this->afterCreating(function (Comment $comment) use ($language): void {
            $languages = array_keys(self::TRANSLATED_SAMPLES);
            $language ??= $languages[random_int(0, count($languages) - 1)];
            $samples = self::TRANSLATED_SAMPLES[$language] ?? self::TRANSLATED_SAMPLES['ru'];
            [$body, $translatedBody] = $samples[random_int(0, count($samples) - 1)];

            /** @var int $maxVersion */
            $maxVersion = $comment->versions()->max('version_number') ?? 0;

            $comment->versions()->create([
                'body' => $body,
                'version_number' => $maxVersion + 1,
                'created_at' => $comment->created_at,
                'detected_language' => $language,
                'translated_body' => $translatedBody,
                'translation_metadata' => ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5'],
                'language_detected_at' => $comment->created_at ?? Date::now(),
                'translated_at' => $comment->created_at ?? Date::now(),
            ]);

            $comment->unsetRelation('latestVersion');
        });
    }

    /**
     * Indicate that the comment is a reply to another comment.
     */
    public function reply(Comment $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'parent_id' => $parent->id,
            'commentable_type' => $parent->commentable_type,
            'commentable_id' => $parent->commentable_id,
        ]);
    }
}
