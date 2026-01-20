<?php

declare(strict_types=1);

use App\Models\User;

describe('password confirmation', function (): void {
    it('can render confirm password screen', function (): void {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/user/confirm-password');

        $response->assertStatus(200);
    });

    it('can confirm password', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/confirm-password', [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    });

    it('does not confirm with invalid password', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/user/confirm-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors();
    });
});
