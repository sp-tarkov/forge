<?php

declare(strict_types=1);

use App\Jobs\ResolveSptVersionsJob;
use App\Jobs\UpdateDownloadsJob;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use App\Services\SptVersionService;

beforeEach(function (): void {
    // Create test data fresh for each test
    $this->sptVersion = SptVersion::factory()->create(['version' => '3.9.0']);
    $this->category = ModCategory::factory()->create(['slug' => 'weapons', 'title' => 'Weapons']);
    $this->user = User::factory()->create();
});

it('returns rss feed with correct content type', function (): void {
    $modVersion = ModVersion::factory()->create(['published_at' => now()]);
    $modVersion->sptVersions()->sync($this->sptVersion->id);
    $mod = $modVersion->mod;
    $mod->update(['published_at' => now()]);

    $response = $this->get(route('mods.rss'));

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
});

it('generates valid rss xml structure', function (): void {
    $mod = Mod::factory()->create([
        'name' => 'Test Mod',
        'description' => 'Test Description',
        'featured' => false,
        'published_at' => now(),
    ]);
    $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id]);
    $modVersion->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss'));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->toBeInstanceOf(SimpleXMLElement::class)
        ->and($xml->getName())->toBe('rss')
        ->and((string) $xml['version'])->toBe('2.0')
        ->and($xml->channel)->not->toBeNull()
        ->and($xml->channel->title)->not->toBeNull()
        ->and($xml->channel->link)->not->toBeNull()
        ->and($xml->channel->description)->not->toBeNull();
});

it('includes mods in rss items', function (): void {
    $mods = Mod::factory()
        ->count(3)
        ->create(['featured' => false, 'published_at' => now()]);

    foreach ($mods as $mod) {
        $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => now()]);
        $modVersion->sptVersions()->sync($this->sptVersion->id);
    }

    $response = $this->get(route('mods.rss'));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    expect($items)->toHaveCount(3);

    // Check that all mod titles are present (order may vary)
    $titles = [];
    foreach ($items as $item) {
        $titles[] = (string) $item->title;
    }

    foreach ($mods as $mod) {
        expect($titles)->toContain($mod->name);
    }
});

it('filters mods by search query', function (): void {
    $mod1 = Mod::factory()->create(['name' => 'Weapon Pack', 'featured' => false, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod1->id, 'published_at' => now()]);
    $modVersion1->sptVersions()->sync($this->sptVersion->id);

    $mod2 = Mod::factory()->create(['name' => 'Armor Set', 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'published_at' => now()]);
    $modVersion2->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss', ['query' => 'Weapon']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->title)->toBe('Weapon Pack');
});

it('filters mods by featured status', function (): void {
    $mod1 = Mod::factory()->create(['name' => 'Featured Mod', 'featured' => true, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod1->id, 'published_at' => now()]);
    $modVersion1->sptVersions()->sync($this->sptVersion->id);

    $mod2 = Mod::factory()->create(['name' => 'Regular Mod', 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'published_at' => now()]);
    $modVersion2->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss', ['featured' => 'only']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->title)->toBe('Featured Mod');
});

it('filters mods by ai generated content status', function (): void {
    $mod1 = Mod::factory()->create(['name' => 'AI Mod', 'contains_ai_content' => true, 'featured' => false, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod1->id, 'published_at' => now()]);
    $modVersion1->sptVersions()->sync($this->sptVersion->id);

    $mod2 = Mod::factory()->create(['name' => 'Human Mod', 'contains_ai_content' => false, 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'published_at' => now()]);
    $modVersion2->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss', ['ai' => 'exclude']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->title)->toBe('Human Mod');
});

it('filters mods by category', function (): void {
    $mod1 = Mod::factory()->create(['name' => 'Weapon Mod', 'category_id' => $this->category->id, 'featured' => false, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod1->id, 'published_at' => now()]);
    $modVersion1->sptVersions()->sync($this->sptVersion->id);

    $mod2 = Mod::factory()->create(['name' => 'Other Mod', 'category_id' => null, 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'published_at' => now()]);
    $modVersion2->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss', ['category' => 'weapons']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->title)->toBe('Weapon Mod');
});

it('sorts mods by downloads when specified', function (): void {
    // Create first mod with high downloads on its version
    $popularMod = Mod::factory()->create([
        'name' => 'Popular Mod XYZ',
        'featured' => false,
        'published_at' => now(),
    ]);
    $modVersion1 = ModVersion::factory()->create([
        'mod_id' => $popularMod->id,
        'published_at' => now(),
        'downloads' => 10000, // High downloads on the version
    ]);
    $modVersion1->sptVersions()->sync($this->sptVersion->id);

    // Create second mod with low downloads on its version
    $unpopularMod = Mod::factory()->create([
        'name' => 'Less Popular Mod ABC',
        'featured' => false,
        'published_at' => now(),
    ]);
    $modVersion2 = ModVersion::factory()->create([
        'mod_id' => $unpopularMod->id,
        'published_at' => now(),
        'downloads' => 10, // Low downloads on the version
    ]);
    $modVersion2->sptVersions()->sync($this->sptVersion->id);

    // Run the job to update mod download counts from their versions
    (new UpdateDownloadsJob)->handle();

    // Get the RSS feed sorted by downloads
    $response = $this->get(route('mods.rss', ['order' => 'downloaded']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    // Verify we have items
    expect(count($items))->toBeGreaterThan(0);

    // Get all mod titles from the RSS feed
    $titles = [];
    foreach ($items as $item) {
        $titles[] = (string) $item->title;
    }

    // Find the positions of our test mods
    $popularPos = array_search('Popular Mod XYZ', $titles, true);
    $unpopularPos = array_search('Less Popular Mod ABC', $titles, true);

    // Both should be present
    expect($popularPos)->toBeInt();
    expect($unpopularPos)->toBeInt();

    // Popular mod should come before unpopular mod
    expect($popularPos)->toBeLessThan($unpopularPos);
});

it('filters mods by spt versions', function (): void {
    // Create unique versions for this test
    $version390unique = SptVersion::factory()->create(['version' => '3.9.1']);
    $version380unique = SptVersion::factory()->create(['version' => '3.8.1']);

    $mod390 = Mod::factory()->create(['name' => 'Unique Mod for 3.9.1', 'featured' => false, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create([
        'mod_id' => $mod390->id,
        'published_at' => now(),
        'spt_version_constraint' => $version390unique->version,
    ]);

    $mod380 = Mod::factory()->create(['name' => 'Unique Mod for 3.8.1', 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create([
        'mod_id' => $mod380->id,
        'published_at' => now(),
        'spt_version_constraint' => $version380unique->version,
    ]);

    // Run the job to resolve SPT versions
    (new ResolveSptVersionsJob)->handle(resolve(SptVersionService::class));

    $response = $this->get(route('mods.rss', ['versions' => '3.9.1']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    // Check that our 3.9.1 mod is present and 3.8.1 mod is not
    $titles = [];
    foreach ($items as $item) {
        $title = (string) $item->title;
        // Only collect titles for our test mods
        if (str_contains($title, 'Unique Mod for')) {
            $titles[] = $title;
        }
    }

    expect($titles)->toContain('Unique Mod for 3.9.1')->not->toContain('Unique Mod for 3.8.1');
});

it('handles multiple spt versions with comma separation', function (): void {
    // Create unique versions for this test
    $version392 = SptVersion::factory()->create(['version' => '3.9.2']);
    $version382 = SptVersion::factory()->create(['version' => '3.8.2']);
    $version372 = SptVersion::factory()->create(['version' => '3.7.2']);

    $mod392Comma = Mod::factory()->create(['name' => 'Comma Test Mod 3.9.2', 'featured' => false, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create([
        'mod_id' => $mod392Comma->id,
        'published_at' => now(),
        'spt_version_constraint' => $version392->version,
    ]);

    $mod382Comma = Mod::factory()->create(['name' => 'Comma Test Mod 3.8.2', 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create([
        'mod_id' => $mod382Comma->id,
        'published_at' => now(),
        'spt_version_constraint' => $version382->version,
    ]);

    $mod372Comma = Mod::factory()->create(['name' => 'Comma Test Mod 3.7.2', 'featured' => false, 'published_at' => now()]);
    $modVersion3 = ModVersion::factory()->create([
        'mod_id' => $mod372Comma->id,
        'published_at' => now(),
        'spt_version_constraint' => $version372->version,
    ]);

    // Run the job to resolve SPT versions
    (new ResolveSptVersionsJob)->handle(resolve(SptVersionService::class));

    $response = $this->get(route('mods.rss', ['versions' => '3.9.2,3.8.2']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    // Find our specific test mods
    $titles = [];
    foreach ($items as $item) {
        $title = (string) $item->title;
        if (str_contains($title, 'Comma Test Mod')) {
            $titles[] = $title;
        }
    }

    // Check that the correct mods are present
    expect($titles)->toContain('Comma Test Mod 3.9.2');
    expect($titles)->toContain('Comma Test Mod 3.8.2');

    // The 3.7.2 mod should NOT be present (not in the filter)
    $has372 = in_array('Comma Test Mod 3.7.2', $titles);
    expect($has372)->toBeFalse();
});

it('shows all versions when versions parameter is all', function (): void {
    // Create unique versions for this test
    $version393 = SptVersion::factory()->create(['version' => '3.9.3']);
    $version383 = SptVersion::factory()->create(['version' => '3.8.3']);

    $mod1 = Mod::factory()->create(['name' => 'Mod for 3.9.3', 'featured' => false, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod1->id, 'published_at' => now()]);
    $modVersion1->sptVersions()->sync($version393->id);

    $mod2 = Mod::factory()->create(['name' => 'Mod for 3.8.3', 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'published_at' => now()]);
    $modVersion2->sptVersions()->sync($version383->id);

    $response = $this->get(route('mods.rss', ['versions' => 'all']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    expect($items)->toHaveCount(2);
});

it('updates feed description based on filters', function (): void {
    $mod = Mod::factory()->create(['name' => 'Test Mod', 'featured' => true, 'published_at' => now()]);
    $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => now()]);
    $modVersion->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss', [
        'query' => 'test',
        'featured' => 'only',
        'ai' => 'exclude',
        'order' => 'downloaded',
    ]));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $description = (string) $xml->channel->description;

    expect($description)->toContain('matching "test"')
        ->toContain('featured mods only')
        ->toContain('excluding AI generated mods')
        ->toContain('sorted by most downloaded');
});

it('describes the feed as sorted by most favourited', function (): void {
    $mod = Mod::factory()->create(['name' => 'Test Mod', 'published_at' => now()]);
    $modVersion = ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => now()]);
    $modVersion->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss', ['order' => 'favourited']));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $description = (string) $xml->channel->description;

    expect($description)->toContain('sorted by most favourited');
});

it('serves the cached feed to guests within the cache window', function (): void {
    $mod = Mod::factory()->create(['name' => 'Original Mod', 'featured' => false, 'published_at' => now()]);
    ModVersion::factory()->create(['mod_id' => $mod->id, 'published_at' => now(), 'spt_version_constraint' => '3.9.0']);

    $firstResponse = $this->get(route('mods.rss'));
    $firstResponse->assertSuccessful()
        ->assertHeader('Cache-Control', 'max-age=300, public');
    expect((string) $firstResponse->getContent())->toContain('Original Mod');

    $newMod = Mod::factory()->create(['name' => 'Newer Mod', 'featured' => false, 'published_at' => now()]);
    ModVersion::factory()->create(['mod_id' => $newMod->id, 'published_at' => now(), 'spt_version_constraint' => '3.9.0']);

    $secondResponse = $this->get(route('mods.rss'));

    $secondResponse->assertSuccessful();
    expect((string) $secondResponse->getContent())->toBe((string) $firstResponse->getContent())
        ->not->toContain('Newer Mod');
});

it('caches the feed separately per query string', function (): void {
    $mod1 = Mod::factory()->create(['name' => 'Weapon Pack', 'featured' => false, 'published_at' => now()]);
    ModVersion::factory()->create(['mod_id' => $mod1->id, 'published_at' => now(), 'spt_version_constraint' => '3.9.0']);

    $mod2 = Mod::factory()->create(['name' => 'Armor Set', 'featured' => false, 'published_at' => now()]);
    ModVersion::factory()->create(['mod_id' => $mod2->id, 'published_at' => now(), 'spt_version_constraint' => '3.9.0']);

    $this->get(route('mods.rss'))->assertSuccessful();

    $filteredResponse = $this->get(route('mods.rss', ['query' => 'Weapon']));

    $filteredResponse->assertSuccessful();
    expect((string) $filteredResponse->getContent())->toContain('Weapon Pack')
        ->not->toContain('Armor Set');
});

it('bypasses the feed cache for moderators without writing to it', function (): void {
    $enabledMod = Mod::factory()->create(['name' => 'Enabled Mod', 'disabled' => false, 'featured' => false, 'published_at' => now()]);
    ModVersion::factory()->create(['mod_id' => $enabledMod->id, 'published_at' => now(), 'spt_version_constraint' => '3.9.0']);

    $disabledMod = Mod::factory()->create(['name' => 'Disabled Mod', 'disabled' => true, 'featured' => false, 'published_at' => now()]);
    ModVersion::factory()->create(['mod_id' => $disabledMod->id, 'published_at' => now(), 'spt_version_constraint' => '3.9.0']);

    $moderator = User::factory()->moderator()->create();
    $moderatorResponse = $this->actingAs($moderator)->get(route('mods.rss'));

    $moderatorResponse->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-cache, private');
    expect((string) $moderatorResponse->getContent())->toContain('Disabled Mod');

    auth()->logout();

    $guestResponse = $this->get(route('mods.rss'));

    $guestResponse->assertSuccessful();
    expect((string) $guestResponse->getContent())->toContain('Enabled Mod')
        ->not->toContain('Disabled Mod');
});

it('respects mod access permissions', function (): void {
    // Test that disabled mods are not shown to regular users
    $mod1 = Mod::factory()->create(['name' => 'Enabled Mod', 'disabled' => false, 'featured' => false, 'published_at' => now()]);
    $modVersion1 = ModVersion::factory()->create(['mod_id' => $mod1->id, 'published_at' => now()]);
    $modVersion1->sptVersions()->sync($this->sptVersion->id);

    $mod2 = Mod::factory()->create(['name' => 'Disabled Mod', 'disabled' => true, 'featured' => false, 'published_at' => now()]);
    $modVersion2 = ModVersion::factory()->create(['mod_id' => $mod2->id, 'published_at' => now()]);
    $modVersion2->sptVersions()->sync($this->sptVersion->id);

    $response = $this->get(route('mods.rss'));

    $response->assertSuccessful();

    $xml = simplexml_load_string((string) $response->getContent());
    $items = $xml->channel->item;

    expect($items)->toHaveCount(1)
        ->and((string) $items[0]->title)->toBe('Enabled Mod');
});
