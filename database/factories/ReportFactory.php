<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Report::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => fake()->randomElement(['App\Models\User', 'App\Models\Mod', 'App\Models\Comment']),
            'reportable_id' => 1,
            'reason' => fake()->randomElement(ReportReason::cases()),
            'context' => fake()->optional()->sentence(),
            'status' => ReportStatus::PENDING,
        ];
    }
}
