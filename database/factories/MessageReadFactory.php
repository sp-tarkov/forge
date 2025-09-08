<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageRead>
 */
class MessageReadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'read_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the message was read recently.
     */
    public function recent(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            ];
        });
    }

    /**
     * Indicate that the message was read a while ago.
     */
    public function old(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
            ];
        });
    }
}
