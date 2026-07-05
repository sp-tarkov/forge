<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AltInvestigationStatus;
use App\Models\AltInvestigationRun;
use App\Models\User;
use App\Support\DataTransferObjects\AltInvestigation;
use App\Support\DataTransferObjects\AltSuspect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AltInvestigationRun>
 */
final class AltInvestigationRunFactory extends Factory
{
    protected $model = AltInvestigationRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'requested_by' => null,
            'status' => AltInvestigationStatus::Pending,
            'results' => null,
            'error' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * A run that is currently processing.
     */
    public function processing(): self
    {
        return $this->state(fn (): array => [
            'status' => AltInvestigationStatus::Processing,
            'started_at' => now(),
        ]);
    }

    /**
     * A completed run with stored results. Defaults to an empty (no-candidate) result when none is provided.
     *
     * @param  array<string, mixed>|null  $results
     */
    public function completed(?array $results = null): self
    {
        return $this->state(fn (): array => [
            'status' => AltInvestigationStatus::Completed,
            'results' => $results ?? new AltInvestigation(
                suspect: new AltSuspect(id: 1, name: 'Suspect', email: 'suspect@example.test', domain: 'example.test', disposableDomain: false),
                candidates: [],
                suspectIpCount: 0,
                excludedNoisyIps: 0,
                truncated: false,
            )->toArray(),
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * A failed run.
     */
    public function failed(string $reason = 'Job failed.'): self
    {
        return $this->state(fn (): array => [
            'status' => AltInvestigationStatus::Failed,
            'error' => $reason,
            'completed_at' => now(),
        ]);
    }
}
