<?php

declare(strict_types=1);

use App\Livewire\Mod\Moderation;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not display on mod detail pages for normal users', function (): void {
    $user = User::factory()->create(['user_role_id' => null]);

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $this
        ->actingAs($user)
        ->get(route('mod.show', [
            'mod' => $mod->id,
            'slug' => $mod->slug,
        ]))
        ->assertDontSeeLivewire(Moderation::class);
});

it('displays on mod detail pages for administrators', function (): void {
    $userRole = UserRole::factory()->administrator()->create();
    $user = User::factory()->create(['user_role_id' => $userRole->id]);

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $this
        ->actingAs($user)
        ->get(route('mod.show', [
            'mod' => $mod->id,
            'slug' => $mod->slug,
        ]))
        ->assertSeeLivewire(Moderation::class);
});

it('mounts the component with the provided mod', function (): void {
    $mod = Mod::factory()->create();
    Livewire::test(Moderation::class, ['mod' => $mod])
        ->assertSet('mod.id', $mod->id);
});

it('allows administrators to delete a mod', function (): void {
    $this->withoutDefer();

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $userRole = UserRole::factory()->administrator()->create();
    $user = User::factory()->create(['user_role_id' => $userRole->id]);

    Livewire::actingAs($user)
        ->test(Moderation::class, ['mod' => $mod])
        ->call('delete')
        ->assertDispatched('mod-delete');

    expect(Mod::query()->find($mod->id))->toBeNull();
});

it('prevents normal users from deleting a mod', function (): void {
    $this->withoutDefer();

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $user = User::factory()->create(['user_role_id' => null]);

    Livewire::actingAs($user)
        ->test(Moderation::class, ['mod' => $mod])
        ->call('delete')
        ->assertForbidden();
});
