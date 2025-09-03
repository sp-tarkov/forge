<?php

declare(strict_types=1);

describe('API Ping', function (): void {
    it('returns a successful response', function (): void {
        $response = $this->getJson('/api/v0/ping');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'pong',
                ],
            ]);
    });
});
