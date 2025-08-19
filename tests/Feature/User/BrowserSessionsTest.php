<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Jetstream\Http\Livewire\LogoutOtherBrowserSessionsForm;
use Livewire\Livewire;

describe('browser sessions', function (): void {
    it('can logout other browser sessions', function (): void {
        $this->actingAs(User::factory()->create());

        Livewire::test(LogoutOtherBrowserSessionsForm::class)
            ->set('password', 'password')
            ->call('logoutOtherBrowserSessions')
            ->assertSuccessful();
    });
});
