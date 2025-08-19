<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommentSubscription;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentSubscription>
 */
class CommentSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commentable_type' => Mod::class,
            'commentable_id' => Mod::factory(),
        ];
    }

    /**
     * Create a subscription for a User commentable.
     */
    public function forUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_type' => User::class,
            'commentable_id' => $user->id ?? User::factory(),
        ]);
    }

    /**
     * Create a subscription for a Mod commentable.
     */
    public function forMod(?Mod $mod = null): static
    {
        return $this->state(fn (array $attributes) => [
            'commentable_type' => Mod::class,
            'commentable_id' => $mod->id ?? Mod::factory(),
        ]);
    }
}
