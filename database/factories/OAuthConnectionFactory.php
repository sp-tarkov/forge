<?php

namespace Database\Factories;

use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OAuthConnection>
 */
class OAuthConnectionFactory extends Factory
{
    protected $model = OAuthConnection::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider_name' => $this->faker->randomElement(['discord', 'google', 'facebook']),
            'provider_id' => (string) $this->faker->unique()->numberBetween(100000, 999999),
            'token' => Str::random(40),
            'refresh_token' => Str::random(40),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
