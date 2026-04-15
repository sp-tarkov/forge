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

            $comment->versions()->create([
                'body' => $versionBody,
                'version_number' => 1,
                'created_at' => $comment->created_at,
            ]);
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
