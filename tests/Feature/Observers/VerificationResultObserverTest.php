<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Events\VerificationResultUpdated;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use Illuminate\Support\Facades\Event;

it('dispatches VerificationResultUpdated event when a verification result is created', function (): void {
    Event::fake([VerificationResultUpdated::class]);

    $result = VerificationResult::factory()->create();

    Event::assertDispatched(
        VerificationResultUpdated::class,
        fn (VerificationResultUpdated $event): bool => $event->id === $result->id
    );
});

it('dispatches VerificationResultUpdated event when a verification result is updated', function (): void {
    $result = VerificationResult::factory()->create();

    Event::fake([VerificationResultUpdated::class]);

    $result->update([
        'status' => VerificationStatus::Running,
    ]);

    Event::assertDispatched(
        VerificationResultUpdated::class,
        fn (VerificationResultUpdated $event): bool => $event->id === $result->id
    );
});

it('dispatches VerificationResultUpdated event when a verification result is deleted', function (): void {
    $result = VerificationResult::factory()->create();

    Event::fake([VerificationResultUpdated::class]);

    $result->delete();

    Event::assertDispatched(
        VerificationResultUpdated::class,
        fn (VerificationResultUpdated $event): bool => $event->id === $result->id
    );
});

it('broadcasts on correct public and private channels', function (): void {
    $result = VerificationResult::factory()->create();

    $event = new VerificationResultUpdated(
        $result->id,
        $result->verifiable_id,
        $result->verifiable_type,
        $result->status->value
    );
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(2);

    $verifiable = $result->verifiable;
    $slug = $result->verifiable_type === ModVersion::class ? 'mod-version' : 'addon-version';

    expect($channels[0]->name)->toBe(sprintf('verification.%s.%d', $slug, $result->verifiable_id));
    expect($channels[1]->name)->toBe('private-admin.verification');
});

it('broadcasts correct minimal payload', function (): void {
    $result = VerificationResult::factory()->create();

    $event = new VerificationResultUpdated(
        $result->id,
        $result->verifiable_id,
        $result->verifiable_type,
        $result->status->value
    );
    $payload = $event->broadcastWith();

    expect($payload)
        ->toHaveKey('id', $result->id)
        ->toHaveKey('verifiable_id', $result->verifiable_id)
        ->toHaveKey('verifiable_type', $result->verifiable_type)
        ->toHaveKey('status', $result->status->value);
});
