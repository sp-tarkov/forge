<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads the user profile page', function (): void {
    $user = User::factory()->create();

    $response = $this->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug]
    ));

    $response->assertStatus(200);
    $response->assertSeeText($user->name);
});

it('shows mods on a profile page', function (): void {
    $user = User::factory()->create();

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create();
    $mod->users()->attach($user->id);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $response = $this->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));

    $response->assertStatus(200);
    $response->assertSeeText($mod->name);
});

it('does not show mods without versions to anonymous users', function (): void {
    $user = User::factory()->create();

    $mod = Mod::factory()->create();
    $mod->users()->attach($user->id);

    $response = $this->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));

    $response->assertStatus(200);
    $response->assertDontSeeText($mod->name);
});

it('shows mods without versions to the author', function (): void {
    $user = User::factory()->create();

    $mod = Mod::factory()->create();
    $mod->users()->attach($user->id);

    $response = $this->actingAs($user)
        ->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

    $response->assertStatus(200);
    $response->assertSeeText($mod->name);
});

it('shows mods without versions to administrators', function (): void {
    $role = UserRole::factory()->administrator()->create();
    $user = User::factory()->create(['user_role_id' => $role->id]);

    $mod = Mod::factory()->create();
    $mod->users()->attach($user->id);

    $response = $this->actingAs($user)
        ->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

    $response->assertStatus(200);
    $response->assertSeeText($mod->name);
});

it('does not show anonymous users disabled mods on a profile page', function (): void {
    $user = User::factory()->create();

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->disabled()->create();
    $mod->users()->attach($user->id);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $response = $this->get(route('user.show', [
        'userId' => $user->id,
        'slug' => $user->slug,
    ]));

    $response->assertStatus(200);
    $response->assertDontSeeText($mod->name);
});

it('shows the author their disabled mods on their profile page', function (): void {
    $user = User::factory()->create();

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->disabled()->create();
    $mod->users()->attach($user->id);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $response = $this->actingAs($user)
        ->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

    $response->assertStatus(200);
    $response->assertSeeText($mod->name);
});

it('shows administrators disabled mods on a profile page', function (): void {
    $role = UserRole::factory()->administrator()->create();
    $user = User::factory()->create(['user_role_id' => $role->id]);

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->disabled()->create();
    $mod->users()->attach($user->id);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $response = $this->actingAs($user)
        ->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

    $response->assertStatus(200);
    $response->assertSeeText($mod->name);
});
