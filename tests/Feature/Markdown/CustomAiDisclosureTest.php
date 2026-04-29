<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;

describe('custom AI disclosure markdown rendering', function (): void {
    it('returns an empty string when the mod has no custom AI disclosure', function (): void {
        $mod = Mod::factory()->create(['custom_ai_disclosure' => null]);

        expect($mod->custom_ai_disclosure_html)->toBe('');
    });

    it('renders markdown formatting in mod custom AI disclosure to safe HTML', function (): void {
        $mod = Mod::factory()->create([
            'custom_ai_disclosure' => "Used **Midjourney** to draft *placeholder* images.\n\n- Refined by hand\n- Reviewed for [accuracy](https://example.com)",
        ]);

        expect($mod->custom_ai_disclosure_html)
            ->toContain('<strong>Midjourney</strong>')
            ->toContain('<em>placeholder</em>')
            ->toContain('<li>Refined by hand</li>')
            ->toContain('href="https://example.com"');
    });

    it('strips disallowed HTML from mod custom AI disclosure via the purifier', function (): void {
        $mod = Mod::factory()->create([
            'custom_ai_disclosure' => 'Used AI <script>alert(1)</script> for copy.',
        ]);

        expect($mod->custom_ai_disclosure_html)
            ->not->toContain('<script>')
            ->not->toContain('alert(1)</script>')
            ->toContain('Used AI');
    });

    it('returns an empty string when the addon has no custom AI disclosure', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->create(['custom_ai_disclosure' => null]);

        expect($addon->custom_ai_disclosure_html)->toBe('');
    });

    it('renders markdown formatting in addon custom AI disclosure to safe HTML', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->create([
            'custom_ai_disclosure' => "Used **AI** for documentation.\n\nSee [docs](https://example.com).",
        ]);

        expect($addon->custom_ai_disclosure_html)
            ->toContain('<strong>AI</strong>')
            ->toContain('href="https://example.com"');
    });
});
