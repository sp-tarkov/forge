<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Seed nine mods covering every status the profile mods tab should account for.
 *
 * Public to all viewers (3): Mod A, Mod H, Mod I.
 * Owner / mod / admin only (6): Mod B, Mod C, Mod D, Mod E, Mod F, Mod G.
 *
 * Mod H carries two additional authors so the LEFT JOIN duplicates rows, exposing the COUNT(*) vs DISTINCT divergence
 * the tab numbers depend on.
 */
function seedUserShowMods(User $owner, User $additionalAuthor, User $otherOwner): void
{
    // A: published mod with one published, enabled version. Visible to everyone.
    $modA = Mod::factory()->for($owner, 'owner')->create([
        'name' => 'Mod A Public',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    ModVersion::factory()->recycle($modA)->create([
        'disabled' => false,
        'published_at' => now()->subDay(),
    ]);

    // B: published mod with no versions. Hidden by the version filter.
    Mod::factory()->for($owner, 'owner')->create([
        'name' => 'Mod B No Versions',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);

    // C: published mod whose only version is disabled.
    $modC = Mod::factory()->for($owner, 'owner')->create([
        'name' => 'Mod C Only Disabled Version',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    ModVersion::factory()->recycle($modC)->create([
        'disabled' => true,
        'published_at' => now()->subDay(),
    ]);

    // D: published mod whose only version is unpublished.
    $modD = Mod::factory()->for($owner, 'owner')->create([
        'name' => 'Mod D Only Unpublished Version',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    ModVersion::factory()->recycle($modD)->create([
        'disabled' => false,
        'published_at' => null,
    ]);

    // E: unpublished mod (no published_at) with a published version.
    $modE = Mod::factory()->for($owner, 'owner')->create([
        'name' => 'Mod E Unpublished',
        'published_at' => null,
        'disabled' => false,
    ]);
    ModVersion::factory()->recycle($modE)->create([
        'disabled' => false,
        'published_at' => now()->subDay(),
    ]);

    // F: scheduled mod (future published_at).
    $modF = Mod::factory()->for($owner, 'owner')->create([
        'name' => 'Mod F Scheduled',
        'published_at' => now()->addWeek(),
        'disabled' => false,
    ]);
    ModVersion::factory()->recycle($modF)->create([
        'disabled' => false,
        'published_at' => now()->subDay(),
    ]);

    // G: disabled mod (mod-level disabled flag).
    $modG = Mod::factory()->for($owner, 'owner')->disabled()->create([
        'name' => 'Mod G Disabled',
        'published_at' => now()->subDay(),
    ]);
    ModVersion::factory()->recycle($modG)->create([
        'disabled' => false,
        'published_at' => now()->subDay(),
    ]);

    // H: public mod owned by the profile, with TWO additional authors. The LEFT JOIN against additional_authors emits
    // 2 rows for this mod; a COUNT(*) without DISTINCT inflates the tab number.
    $modH = Mod::factory()->for($owner, 'owner')->create([
        'name' => 'Mod H Public Multi-Author',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    ModVersion::factory()->recycle($modH)->create([
        'disabled' => false,
        'published_at' => now()->subDay(),
    ]);
    $modH->additionalAuthors()->attach([
        $additionalAuthor->id,
        User::factory()->create()->id,
    ]);

    // I: public mod owned by another user, with the profile owner attached as an additional author. Should appear on
    // the profile owner's page.
    $modI = Mod::factory()->for($otherOwner, 'owner')->create([
        'name' => 'Mod I Authored Elsewhere',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    ModVersion::factory()->recycle($modI)->create([
        'disabled' => false,
        'published_at' => now()->subDay(),
    ]);
    $modI->additionalAuthors()->attach($owner);
}

/**
 * Seed six addons covering every status the addons tab should account for.
 *
 * Public to all viewers (3): Addon A, Addon E, Addon F.
 * Owner / mod / admin only (3): Addon B, Addon C, Addon D.
 */
function seedUserShowAddons(User $owner, User $additionalAuthor, User $otherOwner): void
{
    // Parent mod is intentionally owned by $otherOwner so it doesn't pollute the profile owner's mod count: the addon
    // matrix should only affect addons.
    $parentMod = Mod::factory()->for($otherOwner, 'owner')->create([
        'published_at' => now()->subDay(),
    ]);

    // A: published, enabled, owned.
    $addonA = Addon::factory()->for($parentMod)->for($owner, 'owner')->create([
        'name' => 'Addon A Public',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    AddonVersion::factory()->recycle($addonA)->create();

    // B: disabled.
    $addonB = Addon::factory()->for($parentMod)->for($owner, 'owner')->create([
        'name' => 'Addon B Disabled',
        'published_at' => now()->subDay(),
        'disabled' => true,
    ]);
    AddonVersion::factory()->recycle($addonB)->create();

    // C: unpublished.
    $addonC = Addon::factory()->for($parentMod)->for($owner, 'owner')->create([
        'name' => 'Addon C Unpublished',
        'published_at' => null,
        'disabled' => false,
    ]);
    AddonVersion::factory()->recycle($addonC)->create();

    // D: scheduled (future publish).
    $addonD = Addon::factory()->for($parentMod)->for($owner, 'owner')->create([
        'name' => 'Addon D Scheduled',
        'published_at' => now()->addWeek(),
        'disabled' => false,
    ]);
    AddonVersion::factory()->recycle($addonD)->create();

    // E: published, owned, with TWO additional authors. LEFT JOIN duplicator.
    $addonE = Addon::factory()->for($parentMod)->for($owner, 'owner')->create([
        'name' => 'Addon E Public Multi-Author',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    AddonVersion::factory()->recycle($addonE)->create();
    $addonE->additionalAuthors()->attach([
        $additionalAuthor->id,
        User::factory()->create()->id,
    ]);

    // F: published, owned by another user, owner is additional author.
    $otherMod = Mod::factory()->for($otherOwner, 'owner')->create([
        'published_at' => now()->subDay(),
    ]);
    $addonF = Addon::factory()->for($otherMod)->for($otherOwner, 'owner')->create([
        'name' => 'Addon F Authored Elsewhere',
        'published_at' => now()->subDay(),
        'disabled' => false,
    ]);
    AddonVersion::factory()->recycle($addonF)->create();
    $addonF->additionalAuthors()->attach($owner);
}

describe('profile page access', function (): void {
    it('loads the user profile page', function (): void {
        $user = User::factory()->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($user->name);
    });
});

describe('cover banner', function (): void {
    it('renders the gradient placeholder when no cover photo has been uploaded', function (): void {
        $user = User::factory()->create(['cover_photo_path' => null]);

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSee($user->cover_photo_gradient);
    });

    it('renders the uploaded cover photo when one exists', function (): void {
        Storage::fake('public');

        $user = User::factory()->create(['cover_photo_path' => 'cover-photos/banner.png']);

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSee($user->cover_photo_url, false);
        $response->assertDontSee($user->cover_photo_gradient);
    });
});

describe('mods tab', function (): void {
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();
    });

    it('shows mods on a profile page', function (): void {
        $user = User::factory()->create();

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->create();
        $mod->owner()->associate($user);
        $mod->save();

        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($mod->name);
    });

    it('shows mods where the user is an additional author', function (): void {
        $profileUser = User::factory()->create();
        $owner = User::factory()->create();

        SptVersion::factory()->create(['version' => '1.0.0']);
        $mod = Mod::factory()->for($owner, 'owner')->create();
        $mod->additionalAuthors()->attach($profileUser);

        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $response = $this->get(route('user.show', [
            'userId' => $profileUser->id,
            'slug' => $profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($mod->name);
        $response->assertSeeText('1 Mod');
    });

    describe('mods without versions', function (): void {
        it('does not show mods without versions to anonymous users', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->create();
            $mod->owner()->associate($user);
            $mod->save();

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
            $mod->owner()->associate($user);
            $mod->save();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });

        it('shows mods without versions to administrators', function (): void {
            $user = User::factory()->admin()->create();

            $mod = Mod::factory()->create();
            $mod->owner()->associate($user);
            $mod->save();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });
    });

    describe('disabled mods', function (): void {
        it('does not show anonymous users disabled mods on a profile page', function (): void {
            $user = User::factory()->create();

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->create();
            $mod->owner()->associate($user);
            $mod->save();
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
            $mod->owner()->associate($user);
            $mod->save();
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
            $user = User::factory()->admin()->create();

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->create();
            $mod->owner()->associate($user);
            $mod->save();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });

        it('shows additional authors disabled mods they are authors of', function (): void {
            $author = User::factory()->create();
            $owner = User::factory()->create();

            SptVersion::factory()->create(['version' => '1.0.0']);
            $mod = Mod::factory()->disabled()->for($owner, 'owner')->create();
            $mod->additionalAuthors()->attach($author);
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $response = $this->actingAs($author)
                ->get(route('user.show', [
                    'userId' => $author->id,
                    'slug' => $author->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($mod->name);
        });
    });
});

describe('addons tab', function (): void {
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();
    });

    it('shows addons on a profile page', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $addon = Addon::factory()->create([
            'owner_id' => $user->id,
            'mod_id' => $mod->id,
        ]);
        AddonVersion::factory()->recycle($addon)->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($addon->name);
    });

    it('displays the addons tab with correct count', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();

        $addon1 = Addon::factory()->create([
            'owner_id' => $user->id,
            'mod_id' => $mod->id,
        ]);
        $addon2 = Addon::factory()->create([
            'owner_id' => $user->id,
            'mod_id' => $mod->id,
        ]);
        AddonVersion::factory()->recycle($addon1)->create();
        AddonVersion::factory()->recycle($addon2)->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('2 Addons');
    });

    it('shows addons where the user is an additional author', function (): void {
        $profileUser = User::factory()->create();
        $owner = User::factory()->create();

        $mod = Mod::factory()->for($owner, 'owner')->create();
        ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

        $addon = Addon::factory()
            ->for($mod)
            ->for($owner, 'owner')
            ->published()
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        $addon->additionalAuthors()->attach($profileUser);

        AddonVersion::factory()->for($addon)->create([
            'mod_version_constraint' => '1.0.0',
        ]);

        $response = $this->get(route('user.show', [
            'userId' => $profileUser->id,
            'slug' => $profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText($addon->name);
        $response->assertSeeText('1 Addon');
    });

    describe('disabled addons', function (): void {
        it('does not show anonymous users disabled addons on a profile page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->disabled()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('shows the author their disabled addons on their profile page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->disabled()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });

        it('shows administrators disabled addons on a profile page', function (): void {
            $admin = User::factory()->admin()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->disabled()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($admin)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });

        it('shows additional authors disabled addons they are authors of', function (): void {
            $author = User::factory()->create();
            $owner = User::factory()->create();

            $mod = Mod::factory()->for($owner, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->published()
                ->create([
                    'disabled' => true,
                    'published_at' => now()->subDay(),
                ]);

            $addon->additionalAuthors()->attach($author);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->actingAs($author)
                ->get(route('user.show', [
                    'userId' => $author->id,
                    'slug' => $author->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });
    });

    describe('unpublished addons', function (): void {
        it('does not show anonymous users unpublished addons on a profile page', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->unpublished()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('does not show other users unpublished addons on a profile page', function (): void {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->unpublished()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($otherUser)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('shows the owner their unpublished addons on their profile page', function (): void {
            $user = User::factory()->create();

            $mod = Mod::factory()->for($user, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($user, 'owner')
                ->create([
                    'disabled' => false,
                    'published_at' => null,
                ]);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });

        it('shows administrators unpublished addons on a profile page', function (): void {
            $admin = User::factory()->admin()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->unpublished()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($admin)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });

        it('shows additional authors unpublished addons they are authors of', function (): void {
            $author = User::factory()->create();
            $owner = User::factory()->create();

            $mod = Mod::factory()->for($owner, 'owner')->create();
            ModVersion::factory()->recycle($mod)->create(['spt_version_constraint' => '1.0.0']);

            $addon = Addon::factory()
                ->for($mod)
                ->for($owner, 'owner')
                ->create([
                    'disabled' => false,
                    'published_at' => null,
                ]);

            $addon->additionalAuthors()->attach($author);

            AddonVersion::factory()->for($addon)->create([
                'mod_version_constraint' => '1.0.0',
            ]);

            $response = $this->actingAs($author)
                ->get(route('user.show', [
                    'userId' => $author->id,
                    'slug' => $author->slug,
                ]));

            $response->assertStatus(200);
            $response->assertSeeText($addon->name);
        });
    });

    describe('detached addons', function (): void {
        it('does not show detached addons to anonymous users', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->detached()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('does not show detached addons to the author', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->detached()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($user)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });

        it('does not show detached addons to administrators', function (): void {
            $admin = User::factory()->admin()->create();
            $user = User::factory()->create();
            $mod = Mod::factory()->create();

            $addon = Addon::factory()->detached()->create([
                'owner_id' => $user->id,
                'mod_id' => $mod->id,
            ]);
            AddonVersion::factory()->recycle($addon)->create();

            $response = $this->actingAs($admin)
                ->get(route('user.show', [
                    'userId' => $user->id,
                    'slug' => $user->slug,
                ]));

            $response->assertStatus(200);
            $response->assertDontSeeText($addon->name);
        });
    });

    describe('empty state', function (): void {
        it('shows empty state when user has no addons', function (): void {
            $user = User::factory()->create();

            $response = $this->get(route('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]));

            $response->assertStatus(200);
            $response->assertSeeText('No Addons Yet');
            $response->assertSeeText('This user has not yet published any addons.');
        });
    });
});

describe('mod and addon tab counts', function (): void {
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();

        $this->owner = User::factory()->create();
        $this->stranger = User::factory()->create();
        $this->additionalAuthor = User::factory()->create();
        $this->moderator = User::factory()->moderator()->create();
        $this->admin = User::factory()->admin()->create();
        $this->otherOwner = User::factory()->create();

        seedUserShowMods($this->owner, $this->additionalAuthor, $this->otherOwner);
        seedUserShowAddons($this->owner, $this->additionalAuthor, $this->otherOwner);
    });

    it('keeps mod and addon tab counts in sync with the rendered list for each viewer role', function (
        string $role,
        int $expectedMods,
        int $expectedAddons,
    ): void {
        $viewer = match ($role) {
            'guest' => null,
            'stranger' => $this->stranger,
            'additionalAuthor' => $this->additionalAuthor,
            'owner' => $this->owner,
            'moderator' => $this->moderator,
            'admin' => $this->admin,
        };

        if ($viewer instanceof User) {
            $this->actingAs($viewer);
        }

        $page = Livewire::test('pages::user.show', [
            'userId' => $this->owner->id,
            'slug' => $this->owner->slug,
        ]);

        expect($page->instance()->getModCount())
            ->toBe($expectedMods, sprintf('mod tab count for %s', $role));
        expect($page->instance()->getAddonCount())
            ->toBe($expectedAddons, sprintf('addon tab count for %s', $role));

        $modsTab = Livewire::test('user.show.mods-tab', [
            'userId' => $this->owner->id,
        ]);

        expect($modsTab->instance()->mods->total())
            ->toBe($expectedMods, sprintf('mods paginator total for %s', $role))
            ->and(count($modsTab->instance()->mods->items()))
            ->toBe($expectedMods, sprintf('mods rendered items for %s', $role));

        $addonsTab = Livewire::test('user.show.addons-tab', [
            'userId' => $this->owner->id,
        ]);

        expect($addonsTab->instance()->addons->total())
            ->toBe($expectedAddons, sprintf('addons paginator total for %s', $role))
            ->and(count($addonsTab->instance()->addons->items()))
            ->toBe($expectedAddons, sprintf('addons rendered items for %s', $role));
    })->with([
        'guest' => ['guest', 3, 3],
        'authenticated stranger' => ['stranger', 3, 3],
        'additional author of profile content' => ['additionalAuthor', 3, 3],
        'profile owner' => ['owner', 9, 6],
        'moderator' => ['moderator', 9, 6],
        'admin' => ['admin', 9, 6],
    ]);
});

describe('wall tab', function (): void {
    beforeEach(function (): void {
        Livewire::withoutLazyLoading();
        config()->set('honeypot.enabled', false);
    });

    it('shows the comment component on the wall tab', function (): void {
        $user = User::factory()->create();

        $response = $this->get(route('user.show', [
            'userId' => $user->id,
            'slug' => $user->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeLivewire('comment-component');
    });

    it('can post comments on user profile wall', function (): void {
        $profileUser = User::factory()->create();
        $commenter = User::factory()->create();

        $this->actingAs($commenter);

        Livewire::test('comment-component', ['commentable' => $profileUser])
            ->set('newCommentBody', 'Great profile!')
            ->call('createComment')
            ->assertSet('newCommentBody', '');

        $comment = Comment::query()
            ->where('commentable_type', User::class)
            ->where('commentable_id', $profileUser->id)
            ->where('user_id', $commenter->id)
            ->first();

        expect($comment)->not->toBeNull()
            ->and($comment->body)->toBe('Great profile!');
    });

    it('shows existing comments on user profile wall', function (): void {
        $profileUser = User::factory()->create();
        $commenter = User::factory()->create();

        Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileUser->id,
            'user_id' => $commenter->id,
            'body' => 'Nice work on your mods!',
        ]);

        $response = $this->get(route('user.show', [
            'userId' => $profileUser->id,
            'slug' => $profileUser->slug,
        ]));

        $response->assertStatus(200);
        $response->assertSeeText('Nice work on your mods!');
    });
});
