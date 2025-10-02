<?php

declare(strict_types=1);

use App\Livewire\Page\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Chat Page Tests', function (): void {
    it('requires authentication to access chat page', function (): void {
        $response = $this->get('/chat');

        $response->assertRedirect('/login');
    });

    it('allows authenticated users to access chat page', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/chat');

        $response->assertOk();
        $response->assertSee('wire:id');
        $response->assertSee('wire:snapshot');
    });

    it('renders the chat component correctly', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Chat::class)
            ->assertOk();
    });
});

describe('Chat Browser Tests', function (): void {
    it('performs a browser smoke test for the chat page', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user);

        $page = visit('/chat')
            ->on()->desktop()
            ->inDarkMode();

        $page->assertNoJavascriptErrors();
    });

    it('does not show chat button for guest users on mobile', function (): void {
        $page = visit('/')
            ->on()->mobile();

        // Open mobile menu
        $page->click('[aria-controls="mobile-menu"]')
            ->assertDontSee('Chat')
            ->assertNoJavascriptErrors();
    });

    it('navigates to chat page successfully', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Visit the chat page directly and ensure no errors
        $page = visit('/chat')
            ->on()->desktop();

        // Just verify that the page loads without errors
        $page->assertNoJavascriptErrors();
    });
});
