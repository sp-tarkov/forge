<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ConversationArchive;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationArchive>
 */
class ConversationArchiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'archived_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the conversation was archived recently.
     */
    public function recent(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'archived_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            ];
        });
    }

    /**
     * Indicate that the conversation was archived a while ago.
     */
    public function old(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'archived_at' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
            ];
        });
    }
}
