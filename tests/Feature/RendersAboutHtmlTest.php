<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates about_html when creating a user with about', function (): void {
    $user = User::factory()->create([
        'about' => '**Bold text** and *italic*',
    ]);

    expect($user->about_html)
        ->toContain('<strong>Bold text</strong>')
        ->toContain('<em>italic</em>');
});

it('regenerates about_html when about is updated', function (): void {
    $user = User::factory()->create([
        'about' => 'Original about',
    ]);

    expect($user->about_html)->toContain('Original about');

    $user->update(['about' => 'Updated about']);

    expect($user->fresh()->about_html)
        ->toContain('Updated about')
        ->not->toContain('Original about');
});

it('handles empty about gracefully', function (): void {
    $user = User::factory()->create([
        'about' => '',
    ]);

    expect($user->about_html)->toBe('');
});

it('handles null about gracefully', function (): void {
    $user = User::factory()->create([
        'about' => null,
    ]);

    expect($user->about_html)->toBe('');
});

it('sanitizes html in user about to prevent xss', function (): void {
    $user = User::factory()->create([
        'about' => 'Hello <script>alert("xss")</script> World',
    ]);

    expect($user->about_html)
        ->not->toContain('<script>')
        ->not->toContain('</script>')
        ->toContain('Hello')
        ->toContain('World');
});

it('regenerates about_html using the regenerateAboutHtml method', function (): void {
    $user = User::factory()->create([
        'about' => '# Test',
    ]);

    // Manually clear the cached value
    $user->about_html = null;
    $user->saveQuietly();

    expect($user->fresh()->about_html)->toBeNull();

    $user->regenerateAboutHtml();

    expect($user->fresh()->about_html)->toContain('<h1>Test</h1>');
});
