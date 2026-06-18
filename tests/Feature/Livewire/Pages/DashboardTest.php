<?php

declare(strict_types=1);

use App\Models\User;

describe('dashboard', function (): void {
    it('renders the dashboard page for authenticated users', function (): void {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk();
    });

    it('redirects guests from dashboard to login', function (): void {
        $this->get('/dashboard')->assertRedirect('/login');
    });
});
