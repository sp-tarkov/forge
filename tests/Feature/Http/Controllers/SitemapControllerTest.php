<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\ModList;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;

beforeEach(function (): void {
    SptVersion::factory()->create(['version' => '3.11.4']);
});

/**
 * Create a publicly visible mod: enabled, published, with a published version compatible with the seeded SPT version.
 *
 * @param  array<string, mixed>  $attributes
 */
function publiclyVisibleMod(array $attributes = []): Mod
{
    $mod = Mod::factory()->create($attributes);
    ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '3.11.4']);

    return $mod->refresh();
}

it('returns the sitemap index referencing every child sitemap', function (): void {
    $response = $this->get(route('sitemap.index'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('/sitemap-pages.xml', false)
        ->assertSee('/sitemap-mods.xml', false)
        ->assertSee('/sitemap-addons.xml', false)
        ->assertSee('/sitemap-authors.xml', false)
        ->assertSee('/sitemap-lists.xml', false);

    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->not->toBeFalse();
    expect($xml->getName())->toBe('sitemapindex');
    expect(count($xml->sitemap))->toBe(5);
});

it('lists the static and landing pages', function (): void {
    $response = $this->get(route('sitemap.pages'));

    $response->assertOk()
        ->assertSee(route('home'), false)
        ->assertSee(route('mods'), false)
        ->assertSee(route('static.contact'), false)
        ->assertSee(route('static.terms'), false);

    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->not->toBeFalse();
    expect($xml->getName())->toBe('urlset');
});

it('includes publicly visible mods and excludes hidden ones', function (): void {
    $visible = publiclyVisibleMod();
    $disabled = publiclyVisibleMod(['disabled' => true]);

    $unpublished = Mod::factory()->unpublished()->create();
    ModVersion::factory()->recycle($unpublished)->create(['spt_version_constraint' => '3.11.4']);

    $response = $this->get(route('sitemap.mods'));

    $response->assertOk()
        ->assertSee('/mod/'.$visible->id.'/'.$visible->slug, false)
        ->assertDontSee('/mod/'.$disabled->id.'/'.$disabled->slug, false)
        ->assertDontSee('/mod/'.$unpublished->id.'/'.$unpublished->slug, false);
});

it('includes attached published addons and excludes detached or disabled ones', function (): void {
    $visible = Addon::factory()->create();
    $detached = Addon::factory()->create(['detached_at' => now()]);
    $disabled = Addon::factory()->disabled()->create();

    $response = $this->get(route('sitemap.addons'));

    $response->assertOk()
        ->assertSee('/addon/'.$visible->id.'/'.$visible->slug, false)
        ->assertDontSee('/addon/'.$detached->id.'/'.$detached->slug, false)
        ->assertDontSee('/addon/'.$disabled->id.'/'.$disabled->slug, false);
});

it('includes owners and additional authors of public content but excludes empty and banned profiles', function (): void {
    $owner = User::factory()->create();
    $mod = publiclyVisibleMod(['owner_id' => $owner->id]);

    $coAuthor = User::factory()->create();
    $mod->additionalAuthors()->attach($coAuthor->id);

    $withoutContent = User::factory()->create();

    $bannedOwner = User::factory()->create();
    publiclyVisibleMod(['owner_id' => $bannedOwner->id]);
    $bannedOwner->ban();

    $response = $this->get(route('sitemap.authors'));

    $response->assertOk()
        ->assertSee('/user/'.$owner->id.'/', false)
        ->assertSee('/user/'.$coAuthor->id.'/', false)
        ->assertDontSee('/user/'.$withoutContent->id.'/', false)
        ->assertDontSee('/user/'.$bannedOwner->id.'/', false);
});

it('includes discoverable public lists and excludes private, hidden, disabled, and default lists', function (): void {
    $public = ModList::factory()->create();
    $private = ModList::factory()->private()->create();
    $hidden = ModList::factory()->hidden()->create();
    $disabled = ModList::factory()->disabled()->create();
    $favourites = ModList::factory()->create(['is_default' => true]);

    $response = $this->get(route('sitemap.lists'));

    $response->assertOk()
        ->assertSee('/list/'.$public->id.'/'.$public->slug, false)
        ->assertDontSee('/list/'.$private->id.'/'.$private->slug, false)
        ->assertDontSee('/list/'.$hidden->id.'/'.$hidden->slug, false)
        ->assertDontSee('/list/'.$disabled->id.'/'.$disabled->slug, false)
        ->assertDontSee('/list/'.$favourites->id.'/'.$favourites->slug, false);
});

it('forces the public viewpoint so an admin never leaks unpublished mods', function (): void {
    $admin = User::factory()->admin()->create();

    $unpublished = Mod::factory()->unpublished()->create();
    ModVersion::factory()->recycle($unpublished)->create(['spt_version_constraint' => '3.11.4']);

    $this->actingAs($admin)
        ->get(route('sitemap.mods'))
        ->assertOk()
        ->assertDontSee('/mod/'.$unpublished->id.'/'.$unpublished->slug, false);
});

it('serves robots.txt with the disallow rule and sitemap directive', function (): void {
    $response = $this->get(route('robots'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /mod/download/')
        ->assertSee('Sitemap: '.route('sitemap.index'));
});
