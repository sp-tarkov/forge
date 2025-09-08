<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user1 = User::factory();
        $user2 = User::factory();

        return [
            'user1_id' => $user1,
            'user2_id' => $user2,
            'created_by' => $this->faker->randomElement([$user1, $user2]),
            'last_message_at' => null,
            'last_message_id' => null,
        ];
    }

    /**
     * Indicate that the conversation has messages.
     */
    public function withLastMessage(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'last_message_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            ];
        });
    }

    /**
     * Indicate that the conversation was created recently.
     */
    public function recent(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
                'last_message_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    /**
     * Indicate that the conversation was created long ago.
     */
    public function old(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => $this->faker->dateTimeBetween('-6 months', '-3 months'),
                'last_message_at' => $this->faker->dateTimeBetween('-3 months', '-1 month'),
            ];
        });
    }
}
