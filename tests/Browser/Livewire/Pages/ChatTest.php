<?php

declare(strict_types=1);

use App\Models\User;

describe('Chat page browser smoke tests', function (): void {
    it('loads the chat page without JavaScript errors on desktop', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user);

        visit('/chat')
            ->on()->desktop()
            ->assertNoJavascriptErrors();
    });

    it('does not show chat button for guest users on mobile', function (): void {
        $page = visit('/')
            ->on()->mobile();

        // Open the mobile menu and confirm the chat entry is hidden from guests.
        $page->click('[aria-controls="mobile-menu"]')
            ->assertDontSee('Chat')
            ->assertNoJavascriptErrors();
    });
});
