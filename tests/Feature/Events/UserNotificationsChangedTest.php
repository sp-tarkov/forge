<?php

declare(strict_types=1);

use App\Events\UserNotificationsChanged;
use App\Models\User;

it('broadcasts on the user private channel', function (): void {
    $user = User::factory()->create();

    $channels = new UserNotificationsChanged($user->id)->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect((string) $channels[0]->name)->toBe('private-user.'.$user->id);
});

it('broadcasts an empty payload', function (): void {
    $user = User::factory()->create();

    expect(new UserNotificationsChanged($user->id)->broadcastWith())->toBe([]);
});
