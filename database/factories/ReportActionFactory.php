<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportAction;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportAction>
 */
class ReportActionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ReportAction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'tracking_event_id' => TrackingEvent::factory(),
            'moderator_id' => User::factory(),
        ];
    }
}
