<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Random\RandomException;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    private static string $password;

    /**
     * Define the user's default state.
     *
     * @throws RandomException
     */
    public function definition(): array
    {
        return [
            'name' => $this->generateUniqueName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),

            // TODO: Does Faker have a markdown plugin?
            'about' => fake()->paragraphs(random_int(1, 10), true),

            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(10),
            'user_role_id' => null,
            'profile_photo_path' => null,
            'profile_photo_variants' => null,
            'cover_photo_path' => null,
            'cover_photo_variants' => null,
            'timezone' => fake()->timezone(),
            'email_comment_notifications_enabled' => true,
            'email_reply_notifications_enabled' => true,
            'email_chat_notifications_enabled' => true,
            'last_seen_at' => null,
            'mods_updated_viewed_at' => null,
            'mods_created_viewed_at' => null,
        ];
    }

    /**
     * Indicate that the user's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should have MFA enabled.
     */
    public function withMfa(): static
    {
        return $this->state(fn (array $attributes): array => [
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
        return $this->state(fn (array $attributes): array => [
            'user_role_id' => UserRole::query()
                ->firstOrCreate(
                    ['name' => 'Moderator'],
                    UserRole::factory()->moderator()->make()->attributesToArray()
                )->id,
        ]);
    }

    /**
     * Indicate that the user should be a senior moderator.
     */
    public function seniorModerator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_role_id' => UserRole::query()
                ->firstOrCreate(
                    ['name' => 'Senior Moderator'],
                    UserRole::factory()->seniorModerator()->make()->attributesToArray()
                )->id,
        ]);
    }

    /**
     * Indicate that the user should be a staff member.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_role_id' => UserRole::query()
                ->firstOrCreate(
                    ['name' => 'Staff'],
                    UserRole::factory()->staff()->make()->attributesToArray()
                )->id,
        ]);
    }

    /**
     * Generate a unique username, retrying with a suffix if the name already exists.
     */
    private function generateUniqueName(int $maxAttempts = 10): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $name = fake()->userName();

            // On first attempt, try the name as-is
            if ($attempt === 0) {
                if (! User::query()->where('name', $name)->exists()) {
                    return $name;
                }

                continue;
            }

            // On subsequent attempts, append a number
            $suffixedName = $name.$attempt;
            if (! User::query()->where('name', $suffixedName)->exists()) {
                return $suffixedName;
            }
        }

        // Fallback: append a unique identifier
        return fake()->userName().Str::random(4);
    }
}
