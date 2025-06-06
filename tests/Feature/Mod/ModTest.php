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

    SptVersion::factory()->create(['version' => '3.8.0']);
    $mod = Mod::factory()->create();
    foreach ($versions as $version) {
        ModVersion::factory()->recycle($mod)->create(['version' => $version, 'spt_version_constraint' => '3.8.0']);
    }

    $response = $this->get($mod->detail_url);

    expect($latestVersion)->toBe('2.1.0');

    // Assert the latest version is next to the mod's name
    $response->assertSeeInOrder(explode(' ', sprintf('%s %s', $mod->name, $latestVersion)));

    // Assert the latest version is in the latest download button
    $response->assertSeeText(__('Download Latest Version').sprintf(' (%s)', $latestVersion));
});

it('builds download links using the latest mod version', function (): void {
    SptVersion::factory()->create(['version' => '3.8.0']);
    $mod = Mod::factory()->create(['id' => 1, 'slug' => 'test-mod']);
    ModVersion::factory()->recycle($mod)->create(['version' => '1.2.3', 'spt_version_constraint' => '3.8.0']);
    ModVersion::factory()->recycle($mod)->create(['version' => '1.3.0', 'spt_version_constraint' => '3.8.0']);
    $modVersion = ModVersion::factory()->recycle($mod)->create(['version' => '1.3.4', 'spt_version_constraint' => '3.8.0']);

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

    $response = $this->get($mod->detail_url);
    $response->assertOk();

    // Disable the mod
    $mod->disabled = true;
    $mod->save();

    $notFoundResponse = $this->get($mod->detail_url);
    $notFoundResponse->assertForbidden();
});

it('allows an administrator to view a disabled mod', function (): void {
    $userRole = UserRole::factory()->administrator()->create();
    $this->actingAs(User::factory()->create(['user_role_id' => $userRole->id]));

    SptVersion::factory()->create(['version' => '1.0.0']);
    $mod = Mod::factory()->disabled()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

    $response = $this->get($mod->detail_url);
    $response->assertOk();
});

it('allows a normal user to view a mod in a valid state', function (): void {
    $this->actingAs(User::factory()->create(['user_role_id' => null]));

    SptVersion::factory()->create(['version' => '1.1.1']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']);

    $response = $this->get($mod->detail_url);
    $response->assertOk();
});

it('does not allow a normal user to view a mod without a resolved SPT version', function (): void {
    $this->actingAs(User::factory()->create(['user_role_id' => null]));

    SptVersion::factory()->create(['version' => '9.9.9']);
    $mod = Mod::factory()->create();
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']); // SPT version does not exist

    $response = $this->get($mod->detail_url);
    $response->assertForbidden();
});

it('allows a mod author to view their mod without a resolved SPT version', function (): void {
    $user = User::factory()->create(['user_role_id' => null]);
    $this->actingAs($user);

    SptVersion::factory()->create(['version' => '9.9.9']);
    $mod = Mod::factory()->create();
    $mod->owner()->associate($user);
    $mod->save();

    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.1.1']); // SPT version does not exist

    $response = $this->get($mod->detail_url);
    $response->assertOk();
});

it('does not allow an anonymous user to view an unpublished mod', function (): void {
    SptVersion::factory()->create(['version' => '1.1.1']);

    $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
    $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future

    ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
    ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

    $response = $this->get($mod1->detail_url);
    $response->assertNotFound();

    $response = $this->get($mod2->detail_url);
    $response->assertNotFound();
});

it('does not allow a normal user to view an unpublished mod', function (): void {
    $this->actingAs(User::factory()->create(['user_role_id' => null]));

    SptVersion::factory()->create(['version' => '1.1.1']);

    $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
    $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future

    ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
    ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

    $response = $this->get($mod1->detail_url);
    $response->assertNotFound();

    $response = $this->get($mod2->detail_url);
    $response->assertNotFound();
});

it('allows a owner to view an unpublished mod', function (): void {
    $user = User::factory()->create(['user_role_id' => null]);
    $this->actingAs($user);

    SptVersion::factory()->create(['version' => '1.1.1']);

    $mod1 = Mod::factory()->recycle($user)->create(['published_at' => null]); // Unpublished, owned by the user
    $mod2 = Mod::factory()->recycle($user)->create(['published_at' => now()->addDays(1)]); // Published in the future, owned by the user

    ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
    ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

    $response = $this->get($mod1->detail_url);
    $response->assertOk();

    $response = $this->get($mod2->detail_url);
    $response->assertOk();
});

it('allows an administrator to view an unpublished mod', function (): void {
    $userRole = UserRole::factory()->administrator()->create();
    $user = User::factory()->create(['user_role_id' => $userRole->id]);
    $this->actingAs($user);

    SptVersion::factory()->create(['version' => '1.1.1']);

    $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
    $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future

    ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
    ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

    $response = $this->get($mod1->detail_url);
    $response->assertOk();

    $response = $this->get($mod2->detail_url);
    $response->assertOk();
});

it('allows a mod author to view an unpublished mod', function (): void {
    $user = User::factory()->create(['user_role_id' => null]);
    $this->actingAs($user);

    SptVersion::factory()->create(['version' => '1.1.1']);

    $mod1 = Mod::factory()->create(['published_at' => null]); // Unpublished
    $mod1->authors()->attach($user);

    $mod2 = Mod::factory()->create(['published_at' => now()->addDays(1)]); // Published in the future
    $mod2->authors()->attach($user);

    ModVersion::factory()->recycle($mod1)->create(['spt_version_constraint' => '1.1.1']);
    ModVersion::factory()->recycle($mod2)->create(['spt_version_constraint' => '1.1.1']);

    $response = $this->get($mod1->detail_url);
    $response->assertOk();

    $response = $this->get($mod2->detail_url);
    $response->assertOk();
});
