<?php

declare(strict_types=1);

use App\Enums\AltInvestigationStatus;
use App\Jobs\RunAltDetectionJob;
use App\Models\AltInvestigationRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('creates a pending run and dispatches the job on the alt-detection queue', function (): void {
    Queue::fake();
    $suspect = User::factory()->create();

    $run = AltInvestigationRun::dispatchFor($suspect, 5);

    expect($run->status)->toBe(AltInvestigationStatus::Pending)
        ->and($run->user_id)->toBe($suspect->id)
        ->and($run->requested_by)->toBe(5);

    Queue::assertPushedOn('alt-detection', RunAltDetectionJob::class);
});

it('reuses an in-progress run instead of queueing a duplicate', function (): void {
    Queue::fake();
    $suspect = User::factory()->create();

    $first = AltInvestigationRun::dispatchFor($suspect);
    $second = AltInvestigationRun::dispatchFor($suspect);

    expect($second->id)->toBe($first->id);

    Queue::assertPushed(RunAltDetectionJob::class, 1);
});

it('queues a fresh run once the previous one has completed', function (): void {
    Queue::fake();
    $suspect = User::factory()->create();

    AltInvestigationRun::factory()->completed()->create(['user_id' => $suspect->id]);
    $run = AltInvestigationRun::dispatchFor($suspect);

    expect($run->status)->toBe(AltInvestigationStatus::Pending);

    Queue::assertPushed(RunAltDetectionJob::class, 1);
});

it('returns the most recent run for a suspect', function (): void {
    $suspect = User::factory()->create();

    AltInvestigationRun::factory()->completed()->create(['user_id' => $suspect->id, 'created_at' => now()->subDay()]);
    $newest = AltInvestigationRun::factory()->completed()->create(['user_id' => $suspect->id]);

    expect(AltInvestigationRun::latestFor($suspect)?->id)->toBe($newest->id);
});

it('rebuilds the investigation result from stored json', function (): void {
    $pending = AltInvestigationRun::factory()->create();
    $completed = AltInvestigationRun::factory()->completed()->create();

    expect($pending->result())->toBeNull()
        ->and($completed->result())->not->toBeNull()
        ->and($completed->result()?->candidateCount())->toBe(0);
});

it('exposes status helpers', function (): void {
    expect(AltInvestigationRun::factory()->create()->inProgress())->toBeTrue()
        ->and(AltInvestigationRun::factory()->processing()->create()->isProcessing())->toBeTrue()
        ->and(AltInvestigationRun::factory()->completed()->create()->isCompleted())->toBeTrue()
        ->and(AltInvestigationRun::factory()->failed()->create()->isFailed())->toBeTrue();
});
