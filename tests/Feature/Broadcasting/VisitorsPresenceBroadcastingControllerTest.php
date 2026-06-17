<?php

declare(strict_types=1);

use App\Models\User;

it('does not error when a non-visitors broadcast auth yields a null driver response', function (): void {
    $user = User::factory()->create();

    // The log broadcaster used in tests returns null from validAuthenticationResponse, so the parent
    // authenticate() returns null. The controller's non-nullable return type would otherwise turn that
    // into a TypeError (HTTP 500)... Let's degrade to a clean response instead.
    $response = $this->actingAs($user)->post(route('broadcasting.auth'), [
        'channel_name' => 'private-user.'.$user->id,
        'socket_id' => '1234.5678',
    ]);

    $response->assertStatus(403);
});
