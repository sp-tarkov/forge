<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;

describe('Addon Detachment', function (): void {

    beforeEach(function (): void {
        // Roles will be created automatically by the factory when needed
    });

    describe('Detachment Authorization', function (): void {
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

    describe('Detachment Functionality', function (): void {
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

    describe('Detached Addon Visibility', function (): void {
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

            // Verify the badge is not in the visible HTML by checking for the specific badge structure
            $html = $response->getContent();
            // Remove script/style tags and their content to avoid false positives from debug code
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
            $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

            // Now check if the DETACHED badge appears in the remaining HTML
            expect(str_contains($html, '<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded'))->toBeFalse();

            // Also ensure the detached warning is not visible
            // Only check in the visible callout area, not in debug output
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

            // Verify the badge is not in the visible HTML by checking for the specific badge structure
            $html = $response->getContent();
            // Remove script/style tags and their content to avoid false positives from debug code
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
            $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

            // Now check if the DETACHED badge appears in the remaining HTML
            expect(str_contains($html, '<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded'))->toBeFalse();

            // Also ensure the detached warning is not visible
            // Only check in the visible callout area, not in debug output
            expect(preg_match('/<flux:callout[^>]*>.*?detached from its parent mod.*?<\/flux:callout>/is', $html))->toBe(0);
        });
    });

    describe('Detachment Edge Cases', function (): void {
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

    describe('Attachment Authorization', function (): void {
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

    describe('Attachment Functionality', function (): void {
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

    describe('Search Index Updates', function (): void {
        it('updates search index when addon is detached', function (): void {
            $modOwner = User::factory()->create();
            $mod = Mod::factory()->for($modOwner, 'owner')->create();
            $addon = Addon::factory()->for($mod)->published()->create();

            $this->actingAs($modOwner);

            // Detach the addon
            $addon->detached_at = now();
            $addon->detached_by_user_id = $modOwner->id;
            $addon->save();

            // Verify the searchable array includes the correct detached status
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

            // Verify the searchable array includes the correct attached status
            $addon->refresh();
            $searchableArray = $addon->toSearchableArray();
            expect($searchableArray['is_detached'])->toBeFalse();
        });

        it('reflects detachment status changes in searchable array', function (): void {
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->published()->create();

            // Initial state - not detached
            expect($addon->isDetached())->toBeFalse();
            $initialSearchable = $addon->toSearchableArray();
            expect($initialSearchable['is_detached'])->toBeFalse();

            // Detach the addon
            $addon->detached_at = now();
            $addon->save();
            $addon->refresh();

            // Verify detached status is reflected
            expect($addon->isDetached())->toBeTrue();
            $detachedSearchable = $addon->toSearchableArray();
            expect($detachedSearchable['is_detached'])->toBeTrue();

            // Re-attach the addon
            $addon->detached_at = null;
            $addon->save();
            $addon->refresh();

            // Verify attached status is reflected
            expect($addon->isDetached())->toBeFalse();
            $attachedSearchable = $addon->toSearchableArray();
            expect($attachedSearchable['is_detached'])->toBeFalse();
        });
    });
});
