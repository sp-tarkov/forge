<?php

declare(strict_types=1);

use App\Events\UserUnblocked;
use App\Models\User;

it('broadcasts on the unblocked user private channel', function (): void {
    $unblocker = User::factory()->create();
    $unblocked = User::factory()->create();

    $channels = new UserUnblocked($unblocker, $unblocked)->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect((string) $channels[0]->name)->toBe('private-user.'.$unblocked->id);
});

it('does not reveal the unblocker in the broadcast payload', function (): void {
    $unblocker = User::factory()->create();
    $unblocked = User::factory()->create();

    expect(new UserUnblocked($unblocker, $unblocked)->broadcastWith())->toBe([]);
});
