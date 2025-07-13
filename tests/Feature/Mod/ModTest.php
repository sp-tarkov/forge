<?php

declare(strict_types=1);

use App\Models\License;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Models\UserRole;
use Livewire\Livewire;

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

it('orders mod versions correctly with release versions prioritized over pre-releases', function (): void {
    SptVersion::factory()->create(['version' => '3.8.0']);
    $mod = Mod::factory()->create();

    // Create versions in a mixed order to test sorting
    $version1 = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0-alpha',
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'version_labels' => '-alpha',
        'spt_version_constraint' => '3.8.0',
    ]);

    $version2 = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0',
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'version_labels' => '',
        'spt_version_constraint' => '3.8.0',
    ]);

    $version3 = ModVersion::factory()->recycle($mod)->create([
        'version' => '2.0.0-beta',
        'version_major' => 2,
        'version_minor' => 0,
        'version_patch' => 0,
        'version_labels' => '-beta',
        'spt_version_constraint' => '3.8.0',
    ]);

    $version4 = ModVersion::factory()->recycle($mod)->create([
        'version' => '2.0.0',
        'version_major' => 2,
        'version_minor' => 0,
        'version_patch' => 0,
        'version_labels' => '',
        'spt_version_constraint' => '3.8.0',
    ]);

    $version5 = ModVersion::factory()->recycle($mod)->create([
        'version' => '1.1.0',
        'version_major' => 1,
        'version_minor' => 1,
        'version_patch' => 0,
        'version_labels' => '',
        'spt_version_constraint' => '3.8.0',
    ]);

    // Refresh the mod to clear any cached relationships
    $mod->refresh();

    // Test that versions() relationship returns correctly ordered versions
    $orderedVersions = $mod->versions()->get();

    // Expected order:
    // 1. 2.0.0 (highest major.minor.patch, release version)
    // 2. 2.0.0-beta (same major.minor.patch as above, but pre-release)
    // 3. 1.1.0 (lower major.minor.patch, but release version)
    // 4. 1.0.0 (lower major.minor.patch, release version)
    // 5. 1.0.0-alpha (same major.minor.patch as above, but pre-release)

    expect($orderedVersions->pluck('version')->toArray())->toBe([
        '2.0.0',      // First: highest version, release
        '2.0.0-beta', // Second: same version, pre-release
        '1.1.0',      // Third: lower version, release
        '1.0.0',      // Fourth: lower version, release
        '1.0.0-alpha', // Last: same as above, pre-release
    ]);

    // Test that latestVersion() returns the semantically latest release version
    $latestVersion = $mod->latestVersion;
    expect($latestVersion->version)->toBe('2.0.0');
    expect($latestVersion->version_labels)->toBe('');

    // Test that the first version in the ordered collection matches latestVersion
    expect($orderedVersions->first()->id)->toBe($latestVersion->id);
});

it('correctly handles pre-release labels in alphabetical order', function (): void {
    SptVersion::factory()->create(['version' => '3.8.0']);
    $mod = Mod::factory()->create();

    // Create multiple pre-release versions of the same semantic version
    ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0-rc.1',
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'version_labels' => '-rc.1',
        'spt_version_constraint' => '3.8.0',
    ]);

    ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0-beta',
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'version_labels' => '-beta',
        'spt_version_constraint' => '3.8.0',
    ]);

    ModVersion::factory()->recycle($mod)->create([
        'version' => '1.0.0-alpha',
        'version_major' => 1,
        'version_minor' => 0,
        'version_patch' => 0,
        'version_labels' => '-alpha',
        'spt_version_constraint' => '3.8.0',
    ]);

    $mod->refresh();

    // Test that pre-release versions are ordered alphabetically by label
    $orderedVersions = $mod->versions()->get();

    expect($orderedVersions->pluck('version_labels')->toArray())->toBe([
        '-alpha',  // Alphabetically first
        '-beta',   // Alphabetically second
        '-rc.1',    // Alphabetically third
    ]);

    // Since there's no release version, latestVersion should be the first pre-release
    $latestVersion = $mod->latestVersion;
    expect($latestVersion->version)->toBe('1.0.0-alpha');
});

it('prevents editing mod to use duplicate GUID via Livewire Edit component', function (): void {
    $license = License::factory()->create();
    $user = User::factory()->create();

    // Disable honeypot for testing
    config()->set('honeypot.enabled', false);

    // Create two mods with different GUIDs
    $existingMod = Mod::factory()->create(['guid' => 'com.example.existingmod']);
    $modToEdit = Mod::factory()->recycle($user)->create(['guid' => 'com.example.modtoedit']);

    // Act as the owner of the mod to edit
    $this->actingAs($user);

    // Attempt to edit the second mod to use the first mod's GUID
    Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $modToEdit->id])
        ->set('name', 'Updated Mod')
        ->set('guid', $existingMod->guid)
        ->set('teaser', 'Updated teaser')
        ->set('description', 'Updated description')
        ->set('license', (string) $license->id)
        ->set('sourceCodeUrl', 'https://github.com/example/updated')
        ->set('containsAiContent', false)
        ->set('containsAds', false)
        ->call('save')
        ->assertHasErrors(['guid']);
});

it('allows editing mod to keep its own GUID via Livewire Edit component', function (): void {
    $license = License::factory()->create();
    $user = User::factory()->create();

    // Disable honeypot for testing
    config()->set('honeypot.enabled', false);

    // Create a mod
    $mod = Mod::factory()->recycle($user)->create(['guid' => 'com.example.mymod']);

    // Act as the owner of the mod
    $this->actingAs($user);

    // Edit the mod keeping the same GUID
    Livewire::test(\App\Livewire\Page\Mod\Edit::class, ['modId' => $mod->id])
        ->set('name', 'Updated Mod Name')
        ->set('guid', $mod->guid)
        ->set('teaser', 'Updated teaser')
        ->set('description', 'Updated description')
        ->set('license', (string) $license->id)
        ->set('sourceCodeUrl', 'https://github.com/example/updated')
        ->set('containsAiContent', false)
        ->set('containsAds', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();
});
