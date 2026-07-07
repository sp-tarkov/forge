<?php

declare(strict_types=1);

use App\Models\Mod;

describe('Lazy image rendering in markdown', function (): void {
    it('adds lazy loading attributes to images in mod descriptions', function (): void {
        $mod = Mod::factory()->create([
            'description' => '![A screenshot](https://example.com/screenshot.png)',
        ]);

        expect($mod->description_html)
            ->toContain('loading="lazy"')
            ->toContain('decoding="async"')
            ->toContain('src="https://example.com/screenshot.png"');
    });

    it('adds lazy loading attributes to images inside tabsets', function (): void {
        $description = "## Features {.tabset}\n\n### Screenshots\n\n![Tab screenshot](https://example.com/tab-screenshot.png)\n\n{.endtabset}";

        $mod = Mod::factory()->create(['description' => $description]);

        expect($mod->description_html)
            ->toContain('src="https://example.com/tab-screenshot.png"')
            ->toContain('loading="lazy"')
            ->toContain('decoding="async"');
    });

    it('adds lazy loading attributes to YouTube embed posters', function (): void {
        $mod = Mod::factory()->create([
            'description' => 'https://youtu.be/88Cu_DiZ9YY',
        ]);

        expect($mod->description_html)
            ->toContain('class="youtube-lite"')
            ->toContain('loading="lazy"')
            ->toContain('decoding="async"');
    });

    it('does not add lazy loading attributes to links', function (): void {
        $mod = Mod::factory()->create([
            'description' => '[A link](https://example.com/page)',
        ]);

        expect($mod->description_html)->not->toContain('loading="lazy"');
    });
});
