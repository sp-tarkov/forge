<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sorts addons by download count in descending order', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()
        ->for($user, 'owner')
        ->create(['published_at' => now()]);

    // Create SPT version for mod compatibility
    SptVersion::factory()->create(['version' => '3.8.0']);

    // Create a published mod version so the mod is publicly visible
    // The observer will automatically resolve and attach compatible SPT versions
    ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'version' => '1.0.0',
        'published_at' => now(),
        'spt_version_constraint' => '^3.8.0',
    ]);

    // Create addons with different download counts and published versions
    $addon1 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'Low Downloads Addon',
            'disabled' => false,
        ]);
    $addon1->versions()->create([
        'version' => '1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'mod_version_constraint' => '*',
        'disabled' => false,
        'downloads' => 100,  // Set downloads on the version
    ]);

    $addon2 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'High Downloads Addon',
            'disabled' => false,
        ]);
    $addon2->versions()->create([
        'version' => '1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'mod_version_constraint' => '*',
        'disabled' => false,
        'downloads' => 1000,  // Set downloads on the version
    ]);

    $addon3 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'Medium Downloads Addon',
            'disabled' => false,
        ]);
    $addon3->versions()->create([
        'version' => '1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'mod_version_constraint' => '*',
        'disabled' => false,
        'downloads' => 500,  // Set downloads on the version
    ]);

    // Refresh the addons to get updated download counts
    $addon1->refresh();
    $addon2->refresh();
    $addon3->refresh();

    // Load the mod show page as authenticated user
    $this->actingAs($user);

    Livewire::test('page.mod.show', ['modId' => $mod->id, 'slug' => $mod->slug])
        ->assertSuccessful()
        ->assertSeeInOrder([
            'High Downloads Addon',    // 1000 downloads
            'Medium Downloads Addon',   // 500 downloads
            'Low Downloads Addon',      // 100 downloads
        ]);
});

it('maintains download count sorting when filtering by mod version', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()
        ->for($user, 'owner')
        ->create(['published_at' => now()]);

    // Create SPT version for mod compatibility
    SptVersion::factory()->create(['version' => '3.8.0']);

    // Create a published mod version so the mod is publicly visible
    // The observer will automatically resolve and attach compatible SPT versions
    $modVersion = ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'version' => '1.0.0',
        'published_at' => now(),
        'spt_version_constraint' => '^3.8.0',
    ]);

    // Create addons with different download counts
    $addon1 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'Low Downloads Compatible',
            'disabled' => false,
        ]);

    $addon1->versions()->create([
        'version' => '1.0.0',
        'mod_version_constraint' => '^1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'disabled' => false,
        'downloads' => 50,  // Set downloads on the version
    ]);
    // Observer automatically resolves compatible mod versions

    $addon2 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'High Downloads Compatible',
            'disabled' => false,
        ]);

    $addon2->versions()->create([
        'version' => '1.0.0',
        'mod_version_constraint' => '^1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'disabled' => false,
        'downloads' => 2000,  // Set downloads on the version
    ]);
    // Observer automatically resolves compatible mod versions

    // Refresh the addons to get updated download counts
    $addon1->refresh();
    $addon2->refresh();

    // Create an addon not compatible with this version (shouldn't appear when filtered)
    $addon3 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'Not Compatible Addon',
            'disabled' => false,
        ]);

    // Load the mod show page with version filter as authenticated user
    $this->actingAs($user);

    Livewire::test('page.mod.show', ['modId' => $mod->id, 'slug' => $mod->slug])
        ->set('selectedModVersionId', $modVersion->id)
        ->assertSuccessful()
        ->assertSeeInOrder([
            'High Downloads Compatible',    // 2000 downloads
            'Low Downloads Compatible',      // 50 downloads
        ])
        ->assertDontSee('Not Compatible Addon');
});

it('shows addons sorted by downloads to unauthenticated users', function (): void {
    $user = User::factory()->create();
    $mod = Mod::factory()
        ->for($user, 'owner')
        ->create(['published_at' => now()]);

    // Create SPT version for mod compatibility
    SptVersion::factory()->create(['version' => '3.8.0']);

    // Create a published mod version so the mod is publicly visible
    // The observer will automatically resolve and attach compatible SPT versions
    ModVersion::factory()->create([
        'mod_id' => $mod->id,
        'version' => '1.0.0',
        'published_at' => now(),
        'spt_version_constraint' => '^3.8.0',
    ]);

    // Create addons with different download counts and published versions
    $addon1 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'Few Downloads',
            'disabled' => false,
        ]);
    $addon1->versions()->create([
        'version' => '1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'mod_version_constraint' => '*',
        'disabled' => false,
        'downloads' => 5,  // Set downloads on the version
    ]);

    $addon2 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'Most Downloads',
            'disabled' => false,
        ]);
    $addon2->versions()->create([
        'version' => '1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'mod_version_constraint' => '*',
        'disabled' => false,
        'downloads' => 5000,  // Set downloads on the version
    ]);

    $addon3 = Addon::factory()
        ->published()
        ->for($mod)
        ->for($user, 'owner')
        ->create([
            'name' => 'Some Downloads',
            'disabled' => false,
        ]);
    $addon3->versions()->create([
        'version' => '1.0.0',
        'link' => fake()->url(),
        'published_at' => now(),
        'mod_version_constraint' => '*',
        'disabled' => false,
        'downloads' => 250,  // Set downloads on the version
    ]);

    // Refresh the addons to get updated download counts
    $addon1->refresh();
    $addon2->refresh();
    $addon3->refresh();

    // Verify the addons were created with correct download counts
    expect($addon1->downloads)->toBe(5);
    expect($addon2->downloads)->toBe(5000);
    expect($addon3->downloads)->toBe(250);

    // Load the mod show page as guest (unauthenticated)
    $response = Livewire::test('page.mod.show', ['modId' => $mod->id, 'slug' => $mod->slug])
        ->assertSuccessful();

    $response->assertSeeInOrder([
        'Most Downloads',    // 5000 downloads
        'Some Downloads',    // 250 downloads
        'Few Downloads',     // 5 downloads
    ]);
});
