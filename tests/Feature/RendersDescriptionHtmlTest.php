<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates description_html when creating a mod with description', function (): void {
    $mod = Mod::factory()->create([
        'description' => '# Hello World',
    ]);

    expect($mod->description_html)
        ->toContain('<h1>Hello World</h1>');
});

it('generates description_html when creating a mod version with description', function (): void {
    $mod = Mod::factory()->create();
    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'description' => '**Bold text** and *italic*',
    ]);

    expect($modVersion->description_html)
        ->toContain('<strong>Bold text</strong>')
        ->toContain('<em>italic</em>');
});

it('generates description_html when creating an addon with description', function (): void {
    $mod = Mod::factory()->create();
    $addon = Addon::factory()->create([
        'mod_id' => $mod->id,
        'description' => '- Item 1\n- Item 2',
    ]);

    expect($addon->description_html)
        ->toContain('<li>')
        ->toContain('Item');
});

it('generates description_html when creating an addon version with description', function (): void {
    $mod = Mod::factory()->create();
    $addon = Addon::factory()->create(['mod_id' => $mod->id]);
    $addonVersion = AddonVersion::factory()->create([
        'addon_id' => $addon->id,
        'description' => 'Simple paragraph',
    ]);

    expect($addonVersion->description_html)
        ->toContain('<p>Simple paragraph</p>');
});

it('regenerates description_html when description is updated', function (): void {
    $mod = Mod::factory()->create([
        'description' => 'Original content',
    ]);

    expect($mod->description_html)->toContain('Original content');

    $mod->update(['description' => 'Updated content']);

    expect($mod->fresh()->description_html)
        ->toContain('Updated content')
        ->not->toContain('Original content');
});

it('handles empty description gracefully', function (): void {
    $mod = Mod::factory()->create([
        'description' => '',
    ]);

    expect($mod->description_html)->toBe('');
});

it('renders description_html as empty string when description is missing', function (): void {
    // For models where description can't be null, test with empty string
    $mod = Mod::factory()->create([
        'description' => '',
    ]);

    // The trait should handle empty strings gracefully
    expect($mod->description_html)->toBe('');
    expect($mod->renderDescriptionHtml())->toBe('');
});

it('sanitizes html in markdown to prevent xss', function (): void {
    $mod = Mod::factory()->create([
        'description' => 'Hello <script>alert("xss")</script> World',
    ]);

    // Script tags should be removed by HTMLPurifier (text content is kept, which is safe)
    expect($mod->description_html)
        ->not->toContain('<script>')
        ->not->toContain('</script>')
        ->toContain('Hello')
        ->toContain('World');
});

it('regenerates description_html using the regenerateDescriptionHtml method', function (): void {
    $mod = Mod::factory()->create([
        'description' => '# Test',
    ]);

    // Manually clear the cached value
    $mod->description_html = null;
    $mod->saveQuietly();

    expect($mod->fresh()->description_html)->toBeNull();

    $mod->regenerateDescriptionHtml();

    expect($mod->fresh()->description_html)->toContain('<h1>Test</h1>');
});

it('does not regenerate description_html when other fields are updated', function (): void {
    $mod = Mod::factory()->create([
        'description' => '# Test',
    ]);

    $originalHtml = $mod->description_html;

    // Update a different field
    $mod->update(['name' => 'New Name']);

    expect($mod->fresh()->description_html)->toBe($originalHtml);
});
