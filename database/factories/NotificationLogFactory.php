<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Comment;
use App\Models\NotificationLog;
use App\Models\User;
use App\Notifications\NewCommentNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notifiable_type' => Comment::class,
            'notifiable_id' => Comment::factory(),
            'user_id' => User::factory(),
            'notification_type' => $this->faker->randomElement(NotificationType::cases()),
            'notification_class' => NewCommentNotification::class,
        ];
    }

    /**
     * Indicate that the notification was sent via email only.
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => NotificationType::EMAIL,
        ]);
    }

    /**
     * Indicate that the notification was sent via database only.
     */
    public function database(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => NotificationType::DATABASE,
        ]);
    }

    /**
     * Indicate that the notification was sent via all channels.
     */
    public function all(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_type' => NotificationType::ALL,
        ]);
    }

    /**
     * Indicate that the notification is for a specific user and comment.
     */
    public function forUserAndComment(User $user, Comment $comment): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'notifiable_type' => Comment::class,
            'notifiable_id' => $comment->id,
        ]);
    }
}
