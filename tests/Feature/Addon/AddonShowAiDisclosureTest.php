<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;

describe('Addon Show Page Custom AI Disclosure', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->moderator()->create();
        $this->mod = Mod::factory()->create();
    });

    it('renders the custom AI disclosure as an expandable section with markdown rendered to HTML when present', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => "Used **AI** for documentation.\n\n- See [docs](https://example.com).",
            ]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSeeText('Includes AI Generated Content')
            ->assertSee(':aria-expanded="expanded.toString()"', false)
            ->assertSee('<strong>AI</strong>', false)
            ->assertSee('<li>See <a', false)
            ->assertSee('href="https://example.com"', false);
    });

    it('renders the simple AI disclosure line when AI content is enabled but no custom message exists', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'contains_ai_content' => true,
                'custom_ai_disclosure' => null,
            ]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertSeeText('Includes AI Generated Content')
            ->assertDontSee(':aria-expanded="expanded.toString()"', false);
    });

    it('omits the AI disclosure entirely when AI content is disabled', function (): void {
        $addon = Addon::factory()
            ->for($this->mod)
            ->published()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'contains_ai_content' => false,
                'custom_ai_disclosure' => null,
            ]);

        $response = $this->actingAs($this->user)
            ->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertOk()
            ->assertDontSeeText('Includes AI Generated Content');
    });
});
