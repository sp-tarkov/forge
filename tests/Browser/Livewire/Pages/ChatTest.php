<?php

declare(strict_types=1);

describe('Chat page', function (): void {
    it('does not show chat button for guest users on mobile', function (): void {
        $page = visit('/')
            ->on()->mobile();

        // Open the mobile menu and confirm the chat entry is hidden from guests.
        $page->click('[aria-controls="mobile-menu"]')
            ->assertDontSee('Chat')
            ->assertNoJavascriptErrors();
    });
});
