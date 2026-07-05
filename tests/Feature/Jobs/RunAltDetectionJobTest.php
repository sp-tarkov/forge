<?php

declare(strict_types=1);

use App\Enums\AltInvestigationStatus;
use App\Jobs\RunAltDetectionJob;
use App\Models\AltInvestigationRun;
use App\Models\User;
use App\Services\AltDetectionService;
use Illuminate\Support\Facades\DB;

/**
 * Insert a raw tracking event row for the given account and IP.
 */
function altJobTrackEvent(int $visitorId, string $ip, string $createdAt): void
{
    DB::table('tracking_events')->insert([
        'event_name' => 'login',
        'event_data' => null,
        'is_moderation_action' => false,
        'ip' => $ip,
        'platform' => 'Windows',
        'browser' => 'Chrome',
        'device' => 'desktop',
        'visitor_type' => User::class,
        'visitor_id' => $visitorId,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

it('runs the investigation and stores the completed result', function (): void {
    $suspect = User::factory()->create(['email' => 'live@alpha.test']);
    $candidate = User::factory()->create(['email' => 'other@beta.test']);

    $ip = '203.0.113.20';
    altJobTrackEvent($suspect->id, $ip, '2026-06-01 10:00:00');
    altJobTrackEvent($candidate->id, $ip, '2026-06-01 10:02:00');

    $run = AltInvestigationRun::factory()->create(['user_id' => $suspect->id]);

    new RunAltDetectionJob($run)->handle(resolve(AltDetectionService::class));

    $run->refresh();

    expect($run->status)->toBe(AltInvestigationStatus::Completed)
        ->and($run->completed_at)->not->toBeNull()
        ->and($run->result())->not->toBeNull()
        ->and($run->result()?->candidates[0]->userId)->toBe($candidate->id);
});

it('marks the run failed when the suspect no longer exists', function (): void {
    $run = AltInvestigationRun::factory()->create(['user_id' => 999999]);

    new RunAltDetectionJob($run)->handle(resolve(AltDetectionService::class));

    $run->refresh();

    expect($run->status)->toBe(AltInvestigationStatus::Failed)
        ->and($run->error)->toContain('no longer exists');
});

it('records the failure reason when the job fails', function (): void {
    $run = AltInvestigationRun::factory()->processing()->create();

    new RunAltDetectionJob($run)->failed(new RuntimeException('boom'));

    $run->refresh();

    expect($run->status)->toBe(AltInvestigationStatus::Failed)
        ->and($run->error)->toBe('boom');
});
