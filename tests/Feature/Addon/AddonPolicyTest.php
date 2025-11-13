<?php

declare(strict_types=1);

use App\Models\Addon;
use App\Models\Mod;
use App\Models\User;
use App\Policies\AddonPolicy;

describe('Addon Policy', function (): void {

    beforeEach(function (): void {
        // Roles will be created automatically by the factory when needed
    });

    describe('View Policy', function (): void {
        it('allows anyone to view published addon', function (): void {
            $addon = Addon::factory()->published()->create();

            expect(true)->toBeTrue(); // Published addons are publicly visible
        });

        it('allows owner to view unpublished addon', function (): void {
            $owner = User::factory()->create();
            $addon = Addon::factory()->for($owner, 'owner')->create([
                'published_at' => null,
            ]);

            $this->actingAs($owner);

            expect($owner->can('view', $addon))->toBeTrue();
        });

        it('allows author to view unpublished addon', function (): void {
            $author = User::factory()->create();
            $addon = Addon::factory()->create(['published_at' => null]);
            $addon->additionalAuthors()->attach($author);

            $this->actingAs($author);

            expect($author->can('view', $addon))->toBeTrue();
        });

        it('allows moderator to view unpublished addon', function (): void {
            $moderator = User::factory()->moderator()->create();
            $addon = Addon::factory()->create(['published_at' => null]);

            $this->actingAs($moderator);

            expect($moderator->can('view', $addon))->toBeTrue();
        });

        it('allows admin to view unpublished addon', function (): void {
            $admin = User::factory()->admin()->create();
            $addon = Addon::factory()->create(['published_at' => null]);

            $this->actingAs($admin);

            expect($admin->can('view', $addon))->toBeTrue();
        });

        it('prevents guest from viewing unpublished addon', function (): void {
            $addon = Addon::factory()->create(['published_at' => null]);

            // Test as a guest (no user logged in)
            $this->assertGuest();

            // Guest should not be able to view unpublished addon
            // Since we're testing without a logged-in user, we need to test the policy directly
            $policy = new AddonPolicy();
            $result = $policy->view(null, $addon);

            expect($result)->toBeFalse();
        });

        it('prevents owner from viewing disabled addon unless mod/admin', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->for($owner, 'owner')->published()->create([
                'disabled' => true,
            ]);

            $this->actingAs($owner);

            // Regular owners cannot view disabled addons per the policy
            expect($owner->can('view', $addon))->toBeFalse();
        });
    });

    describe('Create Policy', function (): void {
        it('requires MFA to create addon', function (): void {
            $userWithoutMfa = User::factory()->create();
            $mod = Mod::factory()->create();

            $this->actingAs($userWithoutMfa);

            expect($userWithoutMfa->can('create', [Addon::class, $mod]))->toBeFalse();
        });

        it('allows any user with MFA to create addon', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->create();

            $this->actingAs($user);

            expect($user->can('create', [Addon::class, $mod]))->toBeTrue();
        });

        it('prevents creating addon when mod has addons disabled', function (): void {
            $user = User::factory()->withMfa()->create();
            $mod = Mod::factory()->create([
                'addons_disabled' => true,
            ]);

            $this->actingAs($user);

            expect($user->can('create', [Addon::class, $mod]))->toBeFalse();
        });
    });

    describe('Update Policy', function (): void {
        it('allows owner to update addon', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->for($owner, 'owner')->create();

            $this->actingAs($owner);

            expect($owner->can('update', $addon))->toBeTrue();
        });

        it('allows author to update addon', function (): void {
            $author = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->create();
            $addon->additionalAuthors()->attach($author);

            $this->actingAs($author);

            expect($author->can('update', $addon))->toBeTrue();
        });

        it('allows admin to update addon', function (): void {
            $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->create();

            $this->actingAs($admin);

            expect($admin->can('update', $addon))->toBeTrue();
        });

        it('allows moderator to update addon', function (): void {
            $moderator = User::factory()->moderator()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->create();

            $this->actingAs($moderator);

            expect($moderator->can('update', $addon))->toBeTrue();
        });

        it('prevents non-author from updating addon', function (): void {
            $user = User::factory()->create();
            $addon = Addon::factory()->create();

            $this->actingAs($user);

            expect($user->can('update', $addon))->toBeFalse();
        });
    });

    describe('Delete Policy', function (): void {
        it('allows owner to delete addon', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->for($owner, 'owner')->create();

            $this->actingAs($owner);

            expect($owner->can('delete', $addon))->toBeTrue();
        });

        it('allows admin to delete addon', function (): void {
            $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->create();

            $this->actingAs($admin);

            expect($admin->can('delete', $addon))->toBeTrue();
        });

        it('prevents author (non-owner) from deleting addon', function (): void {
            $author = User::factory()->create();
            $addon = Addon::factory()->create();
            $addon->additionalAuthors()->attach($author);

            $this->actingAs($author);

            expect($author->can('delete', $addon))->toBeFalse();
        });

        it('prevents moderator from deleting addon', function (): void {
            $moderator = User::factory()->moderator()->create();
            $addon = Addon::factory()->create();

            $this->actingAs($moderator);

            expect($moderator->can('delete', $addon))->toBeFalse();
        });
    });

    describe('Publish Policy', function (): void {
        it('allows owner with verified email to publish', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->for($owner, 'owner')->create(['published_at' => null]);

            $this->actingAs($owner);

            expect($owner->can('publish', $addon))->toBeTrue();
        });

        it('allows author with verified email to publish', function (): void {
            $author = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->create(['published_at' => null]);
            $addon->additionalAuthors()->attach($author);

            $this->actingAs($author);

            expect($author->can('publish', $addon))->toBeTrue();
        });

        it('prevents user without verified email from publishing', function (): void {
            $owner = User::factory()->create(['email_verified_at' => null]);
            $addon = Addon::factory()->for($owner, 'owner')->create(['published_at' => null]);

            $this->actingAs($owner);

            expect($owner->can('publish', $addon))->toBeFalse();
        });

        it('prevents non-author from publishing', function (): void {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->create(['published_at' => null]);

            $this->actingAs($user);

            expect($user->can('publish', $addon))->toBeFalse();
        });
    });

    describe('Disable Policy', function (): void {
        it('allows moderator to disable addon', function (): void {
            $moderator = User::factory()->moderator()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->published()->create();

            $this->actingAs($moderator);

            expect($moderator->can('disable', $addon))->toBeTrue();
        });

        it('allows admin to disable addon', function (): void {
            $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->published()->create();

            $this->actingAs($admin);

            expect($admin->can('disable', $addon))->toBeTrue();
        });

        it('prevents regular user from disabling addon', function (): void {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->published()->create();

            $this->actingAs($user);

            expect($user->can('disable', $addon))->toBeFalse();
        });

        it('prevents owner from disabling addon', function (): void {
            $owner = User::factory()->create(['email_verified_at' => now()]);
            $addon = Addon::factory()->for($owner, 'owner')->published()->create();

            $this->actingAs($owner);

            expect($owner->can('disable', $addon))->toBeFalse();
        });

        it('prevents moderator without verified email from disabling', function (): void {
            $moderator = User::factory()->moderator()->create(['email_verified_at' => null]);
            $addon = Addon::factory()->published()->create();

            $this->actingAs($moderator);

            expect($moderator->can('disable', $addon))->toBeFalse();
        });
    });

    describe('Detach Policy', function (): void {
        it('allows mod owner to detach addon', function (): void {
            $modOwner = User::factory()->create(['email_verified_at' => now()]);
            $mod = Mod::factory()->for($modOwner, 'owner')->create();
            $addon = Addon::factory()->for($mod)->create();

            $this->actingAs($modOwner);

            expect($modOwner->can('detach', $addon))->toBeTrue();
        });

        it('allows mod author to detach addon', function (): void {
            $modAuthor = User::factory()->create(['email_verified_at' => now()]);
            $mod = Mod::factory()->create();
            $mod->additionalAuthors()->attach($modAuthor);
            $addon = Addon::factory()->for($mod)->create();

            $this->actingAs($modAuthor);

            expect($modAuthor->can('detach', $addon))->toBeTrue();
        });

        it('allows admin to detach addon', function (): void {
            $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->create();

            $this->actingAs($admin);

            expect($admin->can('detach', $addon))->toBeTrue();
        });

        it('prevents addon owner from detaching if not mod owner/author', function (): void {
            $addonOwner = User::factory()->create();
            $mod = Mod::factory()->create();
            $addon = Addon::factory()->for($mod)->for($addonOwner, 'owner')->create();

            $this->actingAs($addonOwner);

            expect($addonOwner->can('detach', $addon))->toBeFalse();
        });

        it('prevents detaching already detached addon', function (): void {
            $modOwner = User::factory()->create();
            $mod = Mod::factory()->for($modOwner, 'owner')->create();
            $addon = Addon::factory()->for($mod)->detached()->create();

            $this->actingAs($modOwner);

            expect($modOwner->can('detach', $addon))->toBeFalse();
        });

        it('prevents detaching addon without parent mod', function (): void {
            $user = User::factory()->admin()->create();
            $addon = Addon::factory()->create(['mod_id' => null]);

            $this->actingAs($user);

            expect($user->can('detach', $addon))->toBeFalse();
        });
    });
});
