<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Enums\TrackingEventType;
use App\Models\Mod;
use App\Models\Report;
use App\Models\ReportAction;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\ReportActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = resolve(ReportActionService::class);
    $this->adminUser = User::factory()->admin()->create();
});

it('can take an action and link it to a report', function (): void {
    $this->actingAs($this->adminUser);

    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::PENDING,
    ]);

    $reportAction = $this->service->takeAction(
        report: $report,
        eventType: TrackingEventType::MOD_DISABLE,
        trackable: $mod,
        actionCallback: fn () => $mod->update(['disabled' => true]),
        resolveReport: true,
        reason: 'Test reason',
    );

    expect($reportAction)->toBeInstanceOf(ReportAction::class);
    expect($reportAction->report_id)->toBe($report->id);
    expect($reportAction->moderator_id)->toBe($this->adminUser->id);
    expect($reportAction->trackingEvent->reason)->toBe('Test reason');
    expect($mod->fresh()->disabled)->toBeTrue();
    expect($report->fresh()->status)->toBe(ReportStatus::RESOLVED);
});

it('can take an action without resolving the report', function (): void {
    $this->actingAs($this->adminUser);

    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::PENDING,
    ]);

    $this->service->takeAction(
        report: $report,
        eventType: TrackingEventType::MOD_DISABLE,
        trackable: $mod,
        actionCallback: fn () => $mod->update(['disabled' => true]),
        resolveReport: false,
    );

    expect($report->fresh()->status)->toBe(ReportStatus::PENDING);
});

it('can link an existing tracking event to a report', function (): void {
    $this->actingAs($this->adminUser);

    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::PENDING,
    ]);

    $trackingEvent = TrackingEvent::factory()->create([
        'event_name' => TrackingEventType::MOD_DISABLE->value,
        'visitor_id' => $this->adminUser->id,
        'visitable_type' => Mod::class,
        'visitable_id' => $mod->id,
    ]);

    $reportAction = $this->service->linkExistingAction(
        report: $report,
        trackingEvent: $trackingEvent,
    );

    expect($reportAction)->toBeInstanceOf(ReportAction::class);
    expect($reportAction->report_id)->toBe($report->id);
    expect($reportAction->tracking_event_id)->toBe($trackingEvent->id);
    expect($reportAction->moderator_id)->toBe($this->adminUser->id);
});

it('creates tracking event when taking action', function (): void {
    $this->actingAs($this->adminUser);

    $reporter = User::factory()->create();
    $mod = Mod::factory()->create();

    $report = Report::factory()->create([
        'reporter_id' => $reporter->id,
        'reportable_type' => Mod::class,
        'reportable_id' => $mod->id,
        'status' => ReportStatus::PENDING,
    ]);

    $initialEventCount = TrackingEvent::query()->count();

    $this->service->takeAction(
        report: $report,
        eventType: TrackingEventType::MOD_DISABLE,
        trackable: $mod,
        actionCallback: fn (): null => null,
    );

    expect(TrackingEvent::query()->count())->toBe($initialEventCount + 1);

    $event = TrackingEvent::query()->latest()->first();
    expect($event->event_name)->toBe(TrackingEventType::MOD_DISABLE->value);
    expect($event->visitable_type)->toBe(Mod::class);
    expect($event->visitable_id)->toBe($mod->id);
});
