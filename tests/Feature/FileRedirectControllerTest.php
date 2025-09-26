<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\User;

it('redirects file URL to mod page', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()->create([
        'hub_id' => 2973,
        'slug' => 'actual-mod-slug',
        'owner_id' => $user->id,
    ]);

    $response = $this->get('/files/file/2973-battle-ambience');

    $response->assertRedirect(route('mod.show', [
        'modId' => $mod->id,
        'slug' => $mod->slug,
    ]));
});

it('returns 404 when hub_id does not exist', function (): void {
    $response = $this->get('/files/file/9999-nonexistent');

    $response->assertNotFound();
});
