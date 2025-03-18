<?php

declare(strict_types=1);

use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;

it('displays the latest version on the mod detail page', function (): void {
    $versions = [
        '1.0.0',
        '1.1.0',
        '1.2.0',
        '2.0.0',
        '2.1.0',
    ];
    $latestVersion = max($versions);

    $mod = Mod::factory()->create();
    foreach ($versions as $version) {
        ModVersion::factory()->recycle($mod)->create(['version' => $version]);
    }

    $response = $this->get($mod->detailUrl());

    expect($latestVersion)->toBe('2.1.0');

    // Assert the latest version is next to the mod's name
    $response->assertSeeInOrder(explode(' ', sprintf('%s %s', $mod->name, $latestVersion)));

    // Assert the latest version is in the latest download button
    $response->assertSeeText(__('Download Latest Version').sprintf(' (%s)', $latestVersion));
});

it('builds download links using the latest mod version', function (): void {
    $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
    ModVersion::factory()->recycle($mod)->create(['version' => '1.2.3']);
    ModVersion::factory()->recycle($mod)->create(['version' => '1.3.0']);
    $modVersion = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.4']);

    expect($mod->downloadUrl())->toEqual(route('mod.version.download', [
        'mod' => $mod->id,
        'slug' => $mod->slug,
        'version' => $modVersion->version,
    ], absolute: false));
});

it('displays unauthorised if the mod has been disabled', function (): void {
    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $response = $this->get($mod->detailUrl());
    $response->assertOk();

    // Disable the mod
    $mod->disabled = true;
    $mod->save();

    $notFoundResponse = $this->get($mod->detailUrl());
    $notFoundResponse->assertForbidden();
});

it('allows an administrator to view a disabled mod', function (): void {
    $userRole = UserRole::factory()->administrator()->create();
    $this->actingAs(User::factory()->create(['user_role_id' => $userRole->id]));

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->disabled()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $response = $this->get($mod->detailUrl());
    $response->assertOk();
});

it('allows a normal user to view a mod in a valid state', function (): void {
    $this->actingAs(User::factory()->create(['user_role_id' => null]));

    SptVersion::factory()->create(['version' => '1.1.1']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']);

    $response = $this->get($mod->detailUrl());
    $response->assertOk();
});

it('does not allow a normal user to view a mod without a resolved SPT version', function (): void {
    $this->actingAs(User::factory()->create(['user_role_id' => null]));

    SptVersion::factory()->create(['version' => '9.9.9']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']); // SPT version does not exist

    $response = $this->get($mod->detailUrl());
    $response->assertForbidden();
});

it('allows a mod author to view their mod without a resolved SPT version', function (): void {
    $user = User::factory()->create(['user_role_id' => null]);
    $this->actingAs($user);

    SptVersion::factory()->create(['version' => '9.9.9']);
    $mod = Mod::factory()->create();
    $mod->users()->attach($user->id);

    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']); // SPT version does not exist

    $response = $this->get($mod->detailUrl());
    $response->assertOk();
});
