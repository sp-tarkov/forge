<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
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
            'content' => $this->faker->randomElement([
                $this->faker->sentence(),
                $this->faker->paragraph(2),
                $this->faker->text(100),
                'Hey, how are you doing?',
                'Did you see the latest update?',
                'Thanks for your help!',
                'Let me know when you\'re available.',
                'I\'ll check that out, thanks!',
                'Sounds good to me!',
                'Can we discuss this tomorrow?',
                'I agree with your approach.',
                'That\'s a great idea!',
                'I\'m working on it now.',
            ]),
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the message is short.
     */
    public function short(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => $this->faker->randomElement([
                    'Thanks!',
                    'Sure!',
                    'Ok',
                    'Got it',
                    'Nice!',
                    'Cool',
                    'Agreed',
                    'Yes',
                    'No problem',
                    '👍',
                ]),
            ];
        });
    }

    /**
     * Indicate that the message is long.
     */
    public function long(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'content' => $this->faker->paragraphs(3, true),
            ];
        });
    }

    /**
     * Indicate that the message was sent recently.
     */
    public function recent(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            ];
        });
    }

    /**
     * Indicate that the message was sent a while ago.
     */
    public function old(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
            ];
        });
    }
}
