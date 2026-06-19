<?php

declare(strict_types=1);

use App\Models\User;

it('does not error when a non-visitors broadcast auth yields a null driver response', function (): void {
    // Pin the log broadcaster, whose parent authenticate() returns null. CI defaults to the reverb broadcaster,
    // which would instead return a real 200 auth response, so force the driver here to force the controller's
    // null fallback.
    config()->set('broadcasting.default', 'log');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('broadcasting.auth'), [
        'channel_name' => 'private-user.'.$user->id,
        'socket_id' => '1234.5678',
    ]);

    $response->assertStatus(403);
});
