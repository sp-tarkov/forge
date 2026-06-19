<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\SourceCodeLink;
use App\Models\SptVersion;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * Create an addon (skipping model events so the factory's afterCreating SourceCodeLink callback never runs) and then
 * guarantee exactly one source code link exists, which the edit form requires to render.
 *
 * @param  array<string, mixed>  $attributes
 */
function createAddonWithSourceLink(Mod $mod, ?User $owner, array $attributes): Addon
{
    $factory = Addon::factory()->for($mod);

    if ($owner instanceof User) {
        $factory = $factory->for($owner, 'owner');
    }

    $addon = Addon::withoutEvents(fn (): Addon => $factory->create($attributes));

    if ($addon->sourceCodeLinks()->count() === 0) {
        SourceCodeLink::factory()->create([
            'sourceable_type' => Addon::class,
            'sourceable_id' => $addon->id,
        ]);
    }

    return $addon;
}

describe('searchability', function (): void {
    it('should not be searchable when disabled', function (): void {
        $addon = Addon::factory()->create([
            'disabled' => true,
            'published_at' => now(),
        ]);

        expect($addon->shouldBeSearchable())->toBeFalse();
    });

    it('should not be searchable when published_at is null', function (): void {
        $addon = Addon::factory()->create([
            'disabled' => false,
            'published_at' => null,
        ]);

        expect($addon->shouldBeSearchable())->toBeFalse();
    });

    it('should not be searchable when published_at is in the future', function (): void {
        $addon = Addon::factory()->create([
            'disabled' => false,
            'published_at' => now()->addDay(),
        ]);

        expect($addon->shouldBeSearchable())->toBeFalse();
    });

    it('should be searchable when published_at is in the past', function (): void {
        $addon = Addon::factory()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'disabled' => false,
                'published_at' => now()->subDay(),
            ]);

        expect($addon->shouldBeSearchable())->toBeTrue();
    });

    it('should be searchable when published_at is now', function (): void {
        $addon = Addon::factory()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'disabled' => false,
                'published_at' => now(),
            ]);

        expect($addon->shouldBeSearchable())->toBeTrue();
    });

    it('includes thumbnail in searchable array when available', function (): void {
        $addon = Addon::factory()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'thumbnail' => 'addons/test-thumbnail.jpg',
                'published_at' => now(),
            ]);

        $searchArray = $addon->toSearchableArray();

        expect($searchArray)->toHaveKey('thumbnail');
        expect($searchArray['thumbnail'])->toBe($addon->thumbnailUrl);
    });

    it('includes empty string thumbnail in searchable array when not available', function (): void {
        $addon = Addon::factory()
            ->hasVersions(1, ['published_at' => now()])
            ->create([
                'thumbnail' => null,
                'published_at' => now(),
            ]);

        $searchArray = $addon->toSearchableArray();

        expect($searchArray)->toHaveKey('thumbnail');
        expect($searchArray['thumbnail'])->toBe('');
    });
});

describe('detachment authorization', function (): void {
    it('allows mod owner to detach addon', function (): void {
        $modOwner = User::factory()->create(['email_verified_at' => now()]);
        $addonOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->for($addonOwner, 'owner')->create();

        $this->actingAs($modOwner);

        expect($modOwner->can('detach', $addon))->toBeTrue();
    });

    it('allows mod author to detach addon', function (): void {
        $modOwner = User::factory()->create();
        $modAuthor = User::factory()->create(['email_verified_at' => now()]);
        $addonOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $mod->additionalAuthors()->attach($modAuthor);
        $addon = Addon::factory()->for($mod)->for($addonOwner, 'owner')->create();

        $this->actingAs($modAuthor);

        expect($modAuthor->can('detach', $addon))->toBeTrue();
    });

    it('prevents addon owner from detaching if not mod owner/author', function (): void {
        $modOwner = User::factory()->create();
        $addonOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->for($addonOwner, 'owner')->create();

        $this->actingAs($addonOwner);

        expect($addonOwner->can('detach', $addon))->toBeFalse();
    });

    it('allows admin to detach addon', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->create();

        $this->actingAs($admin);

        expect($admin->can('detach', $addon))->toBeTrue();
    });

    it('prevents detaching already detached addon', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->detached()->create();

        $this->actingAs($modOwner);

        expect($modOwner->can('detach', $addon))->toBeFalse();
    });

    it('prevents detaching addon without parent mod', function (): void {
        $user = User::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => null]);

        $this->actingAs($user);

        expect($user->can('detach', $addon))->toBeFalse();
    });
});

describe('detachment functionality', function (): void {
    it('marks addon as detached', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->create([
            'detached_at' => null,
            'detached_by_user_id' => null,
        ]);

        expect($addon->isDetached())->toBeFalse();

        $addon->detached_at = now();
        $addon->detached_by_user_id = $modOwner->id;
        $addon->save();

        $addon->refresh();

        expect($addon->isDetached())->toBeTrue();
        expect($addon->detached_by_user_id)->toBe($modOwner->id);
    });

    it('keeps addon accessible after detachment', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->published()->create();

        $addon->detached_at = now();
        $addon->detached_by_user_id = $modOwner->id;
        $addon->save();

        $addon->refresh();

        expect($addon)->not->toBeNull();
        expect($addon->published_at)->not->toBeNull();
    });

    it('preserves addon data after detachment', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->published()->create([
            'name' => 'Test Addon',
            'downloads' => 100,
        ]);

        $originalName = $addon->name;
        $originalDownloads = $addon->downloads;
        $originalOwnerId = $addon->owner_id;

        $addon->detached_at = now();
        $addon->detached_by_user_id = $modOwner->id;
        $addon->save();

        $addon->refresh();

        expect($addon->name)->toBe($originalName);
        expect($addon->downloads)->toBe($originalDownloads);
        expect($addon->owner_id)->toBe($originalOwnerId);
    });

    it('maintains mod relationship after detachment', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->create();

        $addon->detached_at = now();
        $addon->detached_by_user_id = $modOwner->id;
        $addon->save();

        $addon->refresh();

        expect($addon->mod_id)->toBe($mod->id);
        expect($addon->mod)->not->toBeNull();
    });
});

describe('detached addon visibility', function (): void {
    it('excludes detached addons from mod attachedAddons relationship', function (): void {
        $mod = Mod::factory()->create();
        $attachedAddon = Addon::factory()->for($mod)->published()->create();
        $detachedAddon = Addon::factory()->for($mod)->published()->detached()->create();

        $attachedAddons = $mod->attachedAddons;

        expect($attachedAddons->contains($attachedAddon))->toBeTrue();
        expect($attachedAddons->contains($detachedAddon))->toBeFalse();
    });

    it('includes detached addons in all addons relationship', function (): void {
        $mod = Mod::factory()->create();
        $attachedAddon = Addon::factory()->for($mod)->published()->create();
        $detachedAddon = Addon::factory()->for($mod)->published()->detached()->create();

        $allAddons = $mod->addons;

        expect($allAddons->contains($attachedAddon))->toBeTrue();
        expect($allAddons->contains($detachedAddon))->toBeTrue();
    });

    it('shows detached addons in search results', function (): void {
        $mod = Mod::factory()->create();
        $detachedAddon = Addon::factory()->for($mod)->published()->detached()->create([
            'name' => 'Unique Detached Addon Name',
        ]);

        $searchArray = $detachedAddon->toSearchableArray();

        expect($searchArray['is_detached'])->toBeTrue();
        expect($searchArray['name'])->toBe('Unique Detached Addon Name');
    });

    it('marks detached status in searchable array', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->create();

        $beforeDetach = $addon->toSearchableArray();
        expect($beforeDetach['is_detached'])->toBeFalse();

        $addon->detached_at = now();
        $addon->save();
        $addon->refresh();

        $afterDetach = $addon->toSearchableArray();
        expect($afterDetach['is_detached'])->toBeTrue();
    });

    it('hides detached banner from regular users on addon page', function (): void {
        $addonOwner = User::factory()->create();
        $regularUser = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->for($addonOwner, 'owner')->published()->detached()->create();

        $this->actingAs($regularUser);

        $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));

        // Verify the badge is not in the visible HTML by checking for the specific badge structure. Remove the
        // script/style tags and their content first to avoid false positives from inline debug code.
        $html = $response->getContent();
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        expect(str_contains($html, '<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded'))->toBeFalse();

        // Also ensure the detached warning is not visible in the callout area, not the debug output.
        expect(preg_match('/<flux:callout[^>]*>.*?detached from its parent mod.*?<\/flux:callout>/is', $html))->toBe(0);
    });

    it('shows detached banner to moderators on addon page', function (): void {
        $moderator = User::factory()->moderator()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create();

        $this->actingAs($moderator);

        $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSee('Detached');
    });

    it('shows detached banner to administrators on addon page', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create();

        $this->actingAs($admin);

        $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSee('Detached');
    });

    it('hides detached banner from guests on addon page', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create();

        $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));

        // Verify the badge is not in the visible HTML by checking for the specific badge structure. Remove the
        // script/style tags and their content first to avoid false positives from inline debug code.
        $html = $response->getContent();
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        expect(str_contains($html, '<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded'))->toBeFalse();

        // Also ensure the detached warning is not visible in the callout area, not the debug output.
        expect(preg_match('/<flux:callout[^>]*>.*?detached from its parent mod.*?<\/flux:callout>/is', $html))->toBe(0);
    });
});

describe('detachment edge cases', function (): void {
    it('handles addon with versions after detachment', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->withVersions(3)->create();

        expect($addon->versions)->toHaveCount(3);

        $addon->detached_at = now();
        $addon->save();

        $addon->refresh();

        expect($addon->versions)->toHaveCount(3);
    });

    it('handles addon with authors after detachment', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->create();
        $authors = User::factory()->count(2)->create();
        $addon->additionalAuthors()->attach($authors);

        expect($addon->additionalAuthors)->toHaveCount(2);

        $addon->detached_at = now();
        $addon->save();

        $addon->refresh();

        expect($addon->additionalAuthors)->toHaveCount(2);
    });

    it('records who detached the addon', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->create();

        $addon->detached_at = now();
        $addon->detached_by_user_id = $modOwner->id;
        $addon->save();

        $addon->refresh();

        expect($addon->detachedBy->id)->toBe($modOwner->id);
    });
});

describe('attachment authorization', function (): void {
    it('allows admin to attach detached addon', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->detached()->create();

        $this->actingAs($admin);

        expect($admin->can('attach', $addon))->toBeTrue();
    });

    it('allows moderator to attach detached addon', function (): void {
        $moderator = User::factory()->moderator()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->detached()->create();

        $this->actingAs($moderator);

        expect($moderator->can('attach', $addon))->toBeTrue();
    });

    it('allows mod owner to attach detached addon', function (): void {
        $modOwner = User::factory()->create(['email_verified_at' => now()]);
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->detached()->create();

        $this->actingAs($modOwner);

        expect($modOwner->can('attach', $addon))->toBeTrue();
    });

    it('allows mod author to attach detached addon', function (): void {
        $modOwner = User::factory()->create();
        $modAuthor = User::factory()->create(['email_verified_at' => now()]);
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $mod->additionalAuthors()->attach($modAuthor);
        $addon = Addon::factory()->for($mod)->detached()->create();

        $this->actingAs($modAuthor);

        expect($modAuthor->can('attach', $addon))->toBeTrue();
    });

    it('prevents unauthorized user from attaching detached addon', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->detached()->create();

        $this->actingAs($user);

        expect($user->can('attach', $addon))->toBeFalse();
    });

    it('prevents attaching already attached addon', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->create(['detached_at' => null]);

        $this->actingAs($admin);

        expect($admin->can('attach', $addon))->toBeFalse();
    });

    it('prevents attaching addon without parent mod', function (): void {
        $admin = User::factory()->admin()->create();
        $addon = Addon::factory()->create(['mod_id' => null, 'detached_at' => now()]);

        $this->actingAs($admin);

        expect($admin->can('attach', $addon))->toBeFalse();
    });
});

describe('attachment functionality', function (): void {
    it('removes detached status when attached', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->detached()->create();

        expect($addon->isDetached())->toBeTrue();

        $addon->detached_at = null;
        $addon->detached_by_user_id = null;
        $addon->save();

        $addon->refresh();

        expect($addon->isDetached())->toBeFalse();
        expect($addon->detached_by_user_id)->toBeNull();
    });

    it('makes addon visible in attachedAddons relationship after attach', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create();

        $attachedAddons = $mod->attachedAddons;
        expect($attachedAddons->contains($addon))->toBeFalse();

        $addon->detached_at = null;
        $addon->detached_by_user_id = null;
        $addon->save();

        $mod->refresh();
        $attachedAddons = $mod->attachedAddons;
        expect($attachedAddons->contains($addon))->toBeTrue();
    });

    it('preserves addon data after attachment', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create([
            'name' => 'Test Addon',
            'downloads' => 100,
        ]);

        $originalName = $addon->name;
        $originalDownloads = $addon->downloads;
        $originalOwnerId = $addon->owner_id;

        $addon->detached_at = null;
        $addon->detached_by_user_id = null;
        $addon->save();

        $addon->refresh();

        expect($addon->name)->toBe($originalName);
        expect($addon->downloads)->toBe($originalDownloads);
        expect($addon->owner_id)->toBe($originalOwnerId);
    });

    it('maintains mod relationship after attachment', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->detached()->create();

        $addon->detached_at = null;
        $addon->detached_by_user_id = null;
        $addon->save();

        $addon->refresh();

        expect($addon->mod_id)->toBe($mod->id);
        expect($addon->mod)->not->toBeNull();
    });

    it('updates searchable array after attachment', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create();

        $beforeAttach = $addon->toSearchableArray();
        expect($beforeAttach['is_detached'])->toBeTrue();

        $addon->detached_at = null;
        $addon->detached_by_user_id = null;
        $addon->save();
        $addon->refresh();

        $afterAttach = $addon->toSearchableArray();
        expect($afterAttach['is_detached'])->toBeFalse();
    });
});

describe('search index updates', function (): void {
    it('updates search index when addon is detached', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->published()->create();

        $this->actingAs($modOwner);

        $addon->detached_at = now();
        $addon->detached_by_user_id = $modOwner->id;
        $addon->save();

        $addon->refresh();

        $searchableArray = $addon->toSearchableArray();
        expect($searchableArray['is_detached'])->toBeTrue();
    });

    it('updates search index when addon is attached', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->for($modOwner, 'owner')->create();
        $addon = Addon::factory()->for($mod)->published()->detached()->create();

        expect($addon->toSearchableArray()['is_detached'])->toBeTrue();

        $this->actingAs($modOwner);

        $addon->detached_at = null;
        $addon->detached_by_user_id = null;
        $addon->save();

        $addon->refresh();

        $searchableArray = $addon->toSearchableArray();
        expect($searchableArray['is_detached'])->toBeFalse();
    });

    it('reflects detachment status changes in searchable array', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->for($mod)->published()->create();

        // Initial state: not detached.
        expect($addon->isDetached())->toBeFalse();
        $initialSearchable = $addon->toSearchableArray();
        expect($initialSearchable['is_detached'])->toBeFalse();

        // Detach the addon.
        $addon->detached_at = now();
        $addon->save();
        $addon->refresh();

        expect($addon->isDetached())->toBeTrue();
        $detachedSearchable = $addon->toSearchableArray();
        expect($detachedSearchable['is_detached'])->toBeTrue();

        // Re-attach the addon.
        $addon->detached_at = null;
        $addon->save();
        $addon->refresh();

        expect($addon->isDetached())->toBeFalse();
        $attachedSearchable = $addon->toSearchableArray();
        expect($attachedSearchable['is_detached'])->toBeFalse();
    });
});

describe('AI content lock on edit', function (): void {
    beforeEach(function (): void {
        config()->set('honeypot.enabled', false);
    });

    it('allows staff to lock the contains_ai_content flag and forces it true', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonWithSourceLink($mod, null, [
            'contains_ai_content' => false,
            'contains_ai_content_locked' => false,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContentLocked', true)
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->contains_ai_content_locked)->toBeTrue();
    });

    it('prevents non-staff from changing contains_ai_content when locked', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonWithSourceLink($mod, $owner, [
            'contains_ai_content' => true,
            'contains_ai_content_locked' => true,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContent', false)
            ->set('containsAiContentLocked', false)
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->contains_ai_content_locked)->toBeTrue();
    });

    it('allows staff to unlock the contains_ai_content flag', function (): void {
        $admin = User::factory()->admin()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonWithSourceLink($mod, null, [
            'contains_ai_content' => true,
            'contains_ai_content_locked' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContentLocked', false)
            ->set('containsAiContent', false)
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeFalse();
        expect($addon->contains_ai_content_locked)->toBeFalse();
    });

    it('allows non-staff to update contains_ai_content when not locked', function (): void {
        $owner = User::factory()->withMfa()->create();
        $mod = Mod::factory()->create();
        $addon = createAddonWithSourceLink($mod, $owner, [
            'contains_ai_content' => false,
            'contains_ai_content_locked' => false,
        ]);

        $this->actingAs($owner);

        Livewire::test('pages::addon.edit', ['addonId' => $addon->id])
            ->set('containsAiContent', true)
            ->set('customAiDisclosure', 'Used AI to draft the description.')
            ->call('save')
            ->assertHasNoErrors();

        $addon->refresh();
        expect($addon->contains_ai_content)->toBeTrue();
        expect($addon->contains_ai_content_locked)->toBeFalse();
    });
});

describe('parent mod visibility', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
        $this->user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    });

    it('hides addon from guests when parent mod has no versions', function (): void {
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertForbidden();
    });

    it('hides addon from normal users when parent mod has no versions', function (): void {
        $normalUser = User::factory()->create();
        $mod = Mod::factory()->create();
        $addon = Addon::factory()->create(['mod_id' => $mod->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->actingAs($normalUser)->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertForbidden();
    });

    it('shows addon to owner when parent mod has no versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $mod = Mod::factory()->create(['owner_id' => $this->user->id]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $this->user->id]);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->actingAs($this->user)->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSuccessful();
        $response->assertSee($addon->name);
    });

    it('shows addon to author when parent mod has no versions', function (): void {
        SptVersion::factory()->state(['version' => '3.8.0'])->create();
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        $addon = Addon::factory()->create(['mod_id' => $mod->id, 'owner_id' => $owner->id]);
        $addon->additionalAuthors()->attach($this->user->id);
        AddonVersion::factory()->create(['addon_id' => $addon->id]);

        $response = $this->actingAs($this->user)->get(route('addon.show', [$addon->id, $addon->slug]));

        $response->assertSuccessful();
        $response->assertSee($addon->name);
    });

    it('excludes addon from parent mod addon listing when mod has no versions', function (): void {
        $modWithVersions = Mod::factory()->create();
        $sptVersion = SptVersion::factory()->create(['version' => '3.12.1']);
        ModVersion::factory()->create([
            'mod_id' => $modWithVersions->id,
            'spt_version_constraint' => '^3.12.0',
        ]);
        $visibleAddon = Addon::factory()->create(['mod_id' => $modWithVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $visibleAddon->id]);

        $modWithoutVersions = Mod::factory()->create();
        $hiddenAddon = Addon::factory()->create(['mod_id' => $modWithoutVersions->id]);
        AddonVersion::factory()->create(['addon_id' => $hiddenAddon->id]);

        // Visit the mod with versions: should see its addon.
        $this->actingAs($this->user);
        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', ['modId' => $modWithVersions->id, 'slug' => $modWithVersions->slug])
            ->assertSuccessful()
            ->assertSee($visibleAddon->name);

        // Visit the mod without versions as owner: the addon should not be visible in the public listing even to
        // the owner because the parent mod has no published versions.
        $modOwner = User::factory()->create();
        $modWithoutVersions->update(['owner_id' => $modOwner->id]);
        $hiddenAddon->update(['owner_id' => $modOwner->id]);

        $this->actingAs($modOwner);
        Livewire::withoutLazyLoading()
            ->test('pages::mod.show', ['modId' => $modWithoutVersions->id, 'slug' => $modWithoutVersions->slug])
            ->assertSuccessful();
    });
});

describe('filtering by mod version', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
    });

    it('shows addons that have any version compatible when filtering by mod version', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'version' => '8.7.4',
            'published_at' => now(),
        ]);

        // Create an addon whose latest version IS compatible.
        $compatibleAddon = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Compatible Addon']);

        AddonVersion::factory()->create([
            'addon_id' => $compatibleAddon->id,
            'version' => '2.0.0',
            'published_at' => now(),
            'mod_version_constraint' => '>='.$modVersion->version,
        ]);

        // Create an addon whose latest version is NOT compatible but which has an older compatible version. It
        // should still be shown because it has ANY compatible version.
        $hasOlderCompatibleAddon = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Has Older Compatible Version']);

        AddonVersion::factory()->create([
            'addon_id' => $hasOlderCompatibleAddon->id,
            'version' => '1.0.0',
            'published_at' => now()->subDays(10),
            'version_major' => 1,
            'version_minor' => 0,
            'version_patch' => 0,
            'mod_version_constraint' => '>='.$modVersion->version,
        ]);

        // Create a newer version that is NOT compatible (this becomes the latest by version number).
        AddonVersion::factory()->create([
            'addon_id' => $hasOlderCompatibleAddon->id,
            'version' => '2.0.0',
            'published_at' => now(),
            'version_major' => 2,
            'version_minor' => 0,
            'version_patch' => 0,
            'mod_version_constraint' => '^9.0.0',
        ]);

        // Create an addon with no compatible versions at all.
        $neverCompatibleAddon = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Never Compatible']);

        AddonVersion::factory()->create([
            'addon_id' => $neverCompatibleAddon->id,
            'version' => '1.0.0',
            'published_at' => now(),
            'mod_version_constraint' => '^10.0.0',
        ]);

        $this->actingAs($user);

        Livewire::withoutLazyLoading()
            ->test('mod.show.addons-tab', ['modId' => $mod->id])
            ->set('selectedModVersionId', $modVersion->id)
            ->assertSuccessful()
            ->assertSee('Compatible Addon')
            ->assertSee('Has Older Compatible Version')
            ->assertDontSee('Never Compatible');
    });

    it('shows all addons when no mod version filter is selected', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        $addon1 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Has Compatible Versions']);

        $addon2 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'No Compatible Versions']);

        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'published_at' => now(),
        ]);

        AddonVersion::factory()->create([
            'addon_id' => $addon1->id,
            'published_at' => now(),
            'mod_version_constraint' => '>='.$modVersion->version,
        ]);

        AddonVersion::factory()->create([
            'addon_id' => $addon2->id,
            'published_at' => now(),
            'mod_version_constraint' => '^99.0.0',
        ]);

        $this->actingAs($user);

        Livewire::withoutLazyLoading()
            ->test('mod.show.addons-tab', ['modId' => $mod->id])
            ->assertSuccessful()
            ->assertSee('Has Compatible Versions')
            ->assertSee('No Compatible Versions');
    });
});

describe('sorting by downloads', function (): void {
    beforeEach(function (): void {
        $this->withoutDefer();
    });

    it('sorts addons by download count in descending order', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        SptVersion::factory()->create(['version' => '3.8.0']);

        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'version' => '1.0.0',
            'published_at' => now(),
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addon1 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Low Downloads Addon', 'disabled' => false]);
        $addon1->versions()->create([
            'version' => '1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'mod_version_constraint' => '*',
            'disabled' => false,
            'downloads' => 100,
        ]);

        $addon2 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'High Downloads Addon', 'disabled' => false]);
        $addon2->versions()->create([
            'version' => '1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'mod_version_constraint' => '*',
            'disabled' => false,
            'downloads' => 1000,
        ]);

        $addon3 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Medium Downloads Addon', 'disabled' => false]);
        $addon3->versions()->create([
            'version' => '1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'mod_version_constraint' => '*',
            'disabled' => false,
            'downloads' => 500,
        ]);

        $addon1->refresh();
        $addon2->refresh();
        $addon3->refresh();

        $this->actingAs($user);

        Livewire::withoutLazyLoading()
            ->test('mod.show.addons-tab', ['modId' => $mod->id])
            ->assertSuccessful()
            ->assertSeeInOrder([
                'High Downloads Addon',
                'Medium Downloads Addon',
                'Low Downloads Addon',
            ]);
    });

    it('maintains download count sorting when filtering by mod version', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        SptVersion::factory()->create(['version' => '3.8.0']);

        $modVersion = ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'version' => '1.0.0',
            'published_at' => now(),
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addon1 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Low Downloads Compatible', 'disabled' => false]);
        $addon1->versions()->create([
            'version' => '1.0.0',
            'mod_version_constraint' => '^1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'disabled' => false,
            'downloads' => 50,
        ]);

        $addon2 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'High Downloads Compatible', 'disabled' => false]);
        $addon2->versions()->create([
            'version' => '1.0.0',
            'mod_version_constraint' => '^1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'disabled' => false,
            'downloads' => 2000,
        ]);

        $addon1->refresh();
        $addon2->refresh();

        // Create an addon not compatible with this version (it should not appear when filtered).
        Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Not Compatible Addon', 'disabled' => false]);

        $this->actingAs($user);

        Livewire::withoutLazyLoading()
            ->test('mod.show.addons-tab', ['modId' => $mod->id])
            ->set('selectedModVersionId', $modVersion->id)
            ->assertSuccessful()
            ->assertSeeInOrder([
                'High Downloads Compatible',
                'Low Downloads Compatible',
            ])
            ->assertDontSee('Not Compatible Addon');
    });

    it('shows addons sorted by downloads to unauthenticated users', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()
            ->for($user, 'owner')
            ->create(['published_at' => now()]);

        SptVersion::factory()->create(['version' => '3.8.0']);

        ModVersion::factory()->create([
            'mod_id' => $mod->id,
            'version' => '1.0.0',
            'published_at' => now(),
            'spt_version_constraint' => '^3.8.0',
        ]);

        $addon1 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Few Downloads', 'disabled' => false]);
        $addon1->versions()->create([
            'version' => '1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'mod_version_constraint' => '*',
            'disabled' => false,
            'downloads' => 5,
        ]);

        $addon2 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Most Downloads', 'disabled' => false]);
        $addon2->versions()->create([
            'version' => '1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'mod_version_constraint' => '*',
            'disabled' => false,
            'downloads' => 5000,
        ]);

        $addon3 = Addon::factory()
            ->published()
            ->for($mod)
            ->for($user, 'owner')
            ->create(['name' => 'Some Downloads', 'disabled' => false]);
        $addon3->versions()->create([
            'version' => '1.0.0',
            'link' => fake()->url(),
            'published_at' => now(),
            'mod_version_constraint' => '*',
            'disabled' => false,
            'downloads' => 250,
        ]);

        $addon1->refresh();
        $addon2->refresh();
        $addon3->refresh();

        expect($addon1->downloads)->toBe(5);
        expect($addon2->downloads)->toBe(5000);
        expect($addon3->downloads)->toBe(250);

        Livewire::withoutLazyLoading()
            ->test('mod.show.addons-tab', ['modId' => $mod->id])
            ->assertSuccessful()
            ->assertSeeInOrder([
                'Most Downloads',
                'Some Downloads',
                'Few Downloads',
            ]);
    });
});
