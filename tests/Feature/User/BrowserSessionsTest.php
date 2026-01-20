<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;

describe('browser sessions', function (): void {
    it('can logout other browser sessions', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test('profile.logout-other-browser-sessions-form')
            ->set('password', 'password')
            ->call('logoutOtherBrowserSessions')
            ->assertSuccessful();
    });
});
