<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\RandomException;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the user's default state.
     *
     * @throws RandomException
     */
    public function definition(): array
    {
        return [
            'name' => fake()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),

            // TODO: Does Faker have a markdown plugin?
            'about' => fake()->paragraphs(random_int(1, 10), true),

            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'user_role_id' => null,
            'profile_photo_path' => null,
            'timezone' => fake()->timezone(),
            'email_comment_notifications_enabled' => true,
            'email_chat_notifications_enabled' => true,
        ];
    }

    /**
     * Indicate that the user's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should have MFA enabled.
     */
    public function withMfa(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('fake-two-factor-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user should be a moderator.
     */
    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_role_id' => \App\Models\UserRole::where('name', 'Moderator')->first()?->id,
        ]);
    }

    /**
     * Indicate that the user should be an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_role_id' => \App\Models\UserRole::where('name', 'Administrator')->first()?->id,
        ]);
    }
}
