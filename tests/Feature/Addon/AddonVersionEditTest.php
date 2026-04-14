<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

describe('Addon Version Edit Form', function (): void {

    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);

        Http::fake([
            '*' => Http::response('', 200, [
                'content-type' => 'application/octet-stream',
                'content-disposition' => 'attachment; filename="addon.7z"',
                'content-length' => '1048576',
            ]),
        ]);
    });

    it('allows clearing the published_at date when editing an addon version', function (): void {
        $user = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create([
            'mod_id' => $mod->id,
            'owner_id' => $user->id,
        ]);
        $addonVersion = AddonVersion::factory()->create([
            'addon_id' => $addon->id,
            'link' => 'https://example.com/addon.7z',
            'published_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test('pages::addon-version.edit', ['addon' => $addon, 'addonVersion' => $addonVersion])
            ->assertNotSet('publishedAtDate', null)
            ->set('publishedAtDate', '')
            ->set('publishedAtTime', '')
            ->set('virusTotalLinks', [
                ['url' => 'https://www.virustotal.com/gui/file/abc123', 'label' => 'Test Scan'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $addonVersion->refresh();
        expect($addonVersion->published_at)->toBeNull();
    });
});
