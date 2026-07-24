<?php

declare(strict_types=1);

use App\Events\UserBlocked;
use App\Models\User;

it('broadcasts on the blocked user private channel', function (): void {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    $channels = new UserBlocked($blocker, $blocked)->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect((string) $channels[0]->name)->toBe('private-user.'.$blocked->id);
});

it('does not reveal the blocker in the broadcast payload', function (): void {
    $blocker = User::factory()->create();
    $blocked = User::factory()->create();

    expect(new UserBlocked($blocker, $blocked)->broadcastWith())->toBe([]);
});
