<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
final class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => fake()->randomElement([User::class, Mod::class, Comment::class]),
            'reportable_id' => 1,
            'reason' => fake()->randomElement(ReportReason::cases()),
            'context' => fake()->optional()->sentence(),
            'status' => ReportStatus::PENDING,
        ];
    }

    /**
     * Indicate that the report has been assigned to a moderator.
     */
    public function assigned(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'assignee_id' => $user instanceof User ? $user->id : User::factory(),
        ]);
    }

    /**
     * Indicate that the report has been resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => ReportStatus::RESOLVED,
        ]);
    }

    /**
     * Indicate that the report has been dismissed.
     */
    public function dismissed(): static
    {
        return $this->state(fn (): array => [
            'status' => ReportStatus::DISMISSED,
        ]);
    }
}
