<?php

declare(strict_types=1);

use App\Models\SptVersion;

beforeEach(function (): void {
    SptVersion::factory()->create(['version' => '3.9.0']);
});

describe('Code Highlighting', function (): void {
    it('highlights code blocks on a full page load of the developers page', function (): void {
        $page = visit('/developers');

        $page->assertPresent('.static-content pre code.hljs')
            ->assertNoJavaScriptErrors();
    });

    it('highlights code blocks after a wire:navigate visit to the developers page', function (): void {
        $page = visit('/');

        $page->click('footer a[href$="/developers"]')
            ->assertSee('The Forge API')
            ->assertPresent('.static-content pre code.hljs')
            ->assertNoJavaScriptErrors();
    });
});
