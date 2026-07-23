<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Models\Addon;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    // Enable Akismet so the CommentObserver dispatches the queued spam check (which Queue::fake() then swallows)
    // instead of marking the comment clean inline. The factory-seeded spam_status values these policy tests rely on
    // would otherwise be overwritten by the inline path.
    Config::set('akismet.enabled', true);

    Queue::fake(); // Prevent spam check jobs from running

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create moderator with proper role
    $this->moderator = User::factory()->moderator()->create();

    // Create admin with proper role
    $this->admin = User::factory()->admin()->create();

    $this->mod = Mod::factory()->create();
    $this->addon = Addon::factory()->create();
    $this->policy = new CommentPolicy;
});

describe('seeRibbon Policy Method', function (): void {
    it('returns false for guests', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
        ]);

        expect($this->policy->seeRibbon(null, $comment))->toBeFalse();
    });

    it('returns false for regular users', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
        ]);

        expect($this->policy->seeRibbon($this->user, $comment))->toBeFalse();
    });

    it('returns false for clean comments even for moderators', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::CLEAN->value,
        ]);

        expect($this->policy->seeRibbon($this->moderator, $comment))->toBeFalse();
        expect($this->policy->seeRibbon($this->admin, $comment))->toBeFalse();
    });

    it('returns true for spam comments to moderators', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'user_id' => $this->user->id,
        ]);

        expect($this->policy->seeRibbon($this->moderator, $comment))->toBeTrue();
    });

    it('returns true for spam comments to admins', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::SPAM->value,
            'user_id' => $this->user->id,
        ]);

        expect($this->policy->seeRibbon($this->admin, $comment))->toBeTrue();
    });

    it('returns true for pending comments to moderators who are not the author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->user->id,
        ]);

        expect($this->policy->seeRibbon($this->moderator, $comment))->toBeTrue();
    });

    it('returns false for pending comments to moderators who are the author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->moderator->id,
        ]);

        expect($this->policy->seeRibbon($this->moderator, $comment))->toBeFalse();
    });

    it('returns true for pending comments to admins who are not the author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->user->id,
        ]);

        expect($this->policy->seeRibbon($this->admin, $comment))->toBeTrue();
    });

    it('returns false for pending comments to admins who are the author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'spam_status' => SpamStatus::PENDING->value,
            'user_id' => $this->admin->id,
        ]);

        expect($this->policy->seeRibbon($this->admin, $comment))->toBeFalse();
    });
});

describe('modOwnerSoftDelete Policy Method', function (): void {
    it('returns false for already deleted comments', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerSoftDelete($this->user, $comment))->toBeFalse();
    });

    it('returns false for moderators who are not mod owners or authors', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($this->moderator, $comment))->toBeFalse();
    });

    it('returns false for admins who are not mod owners or authors', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($this->admin, $comment))->toBeFalse();
    });

    it('returns false for regular users who are not mod owners or authors', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($this->user, $comment))->toBeFalse();
    });

    it('returns true for mod owners', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($modOwner, $comment))->toBeTrue();
    });

    it('returns true for mod authors', function (): void {
        $modAuthor = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($modAuthor);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($modAuthor, $comment))->toBeTrue();
    });

    it('returns false for users who do not own the profile being commented on', function (): void {
        $profileOwner = User::factory()->create();
        $comment = Comment::factory()->for($profileOwner, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($this->user, $comment))->toBeFalse();
    });

    it('returns false for mod owners on different mods', function (): void {
        $modOwner = User::factory()->create();
        $mod1 = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $mod2 = Mod::factory()->create();
        $comment = Comment::factory()->for($mod2, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($modOwner, $comment))->toBeFalse();
    });

    it('returns true for administrators who are also mod owners', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->admin->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($this->admin, $comment))->toBeTrue();
    });

    it('returns true for moderators who are also mod authors', function (): void {
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($this->moderator);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($this->moderator, $comment))->toBeTrue();
    });

    it('returns true for user profile owners on their own profile comments', function (): void {
        $profileOwner = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $commenter->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($profileOwner, $comment))->toBeTrue();
    });

    it('returns false for users on other users profile comments', function (): void {
        $profileOwner = User::factory()->create();
        $otherUser = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $commenter->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($otherUser, $comment))->toBeFalse();
    });

    it('returns true for administrators who own profile being commented on', function (): void {
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $this->admin->id,
            'user_id' => $this->user->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($this->admin, $comment))->toBeTrue();
    });

    it('returns false for mod owners trying to delete comments made by administrators', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'user_id' => $this->admin->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($modOwner, $comment))->toBeFalse();
    });

    it('returns false for mod owners trying to delete comments made by moderators', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'user_id' => $this->moderator->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($modOwner, $comment))->toBeFalse();
    });

    it('returns false for profile owners trying to delete comments made by administrators', function (): void {
        $profileOwner = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $this->admin->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($profileOwner, $comment))->toBeFalse();
    });

    it('returns false for profile owners trying to delete comments made by moderators', function (): void {
        $profileOwner = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $this->moderator->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($profileOwner, $comment))->toBeFalse();
    });
});

describe('modOwnerRestore Policy Method', function (): void {
    it('returns false for non-deleted comments', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->modOwnerRestore($this->user, $comment))->toBeFalse();
    });

    it('returns true for mod owners on deleted mod comments', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($modOwner, $comment))->toBeTrue();
    });

    it('returns true for mod authors on deleted mod comments', function (): void {
        $modAuthor = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($modAuthor);
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($modAuthor, $comment))->toBeTrue();
    });

    it('returns true for profile owners on deleted profile comments', function (): void {
        $profileOwner = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $this->user->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($profileOwner, $comment))->toBeTrue();
    });

    it('returns false for users who do not own the mod or profile', function (): void {
        $profileOwner = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $this->user->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($this->user, $comment))->toBeFalse();
    });

    it('returns true for administrators who own the profile being commented on', function (): void {
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $this->admin->id,
            'user_id' => $this->user->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($this->admin, $comment))->toBeTrue();
    });

    it('returns true for moderators who are mod authors on deleted mod comments', function (): void {
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($this->moderator);
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($this->moderator, $comment))->toBeTrue();
    });

    it('returns false for mod owners trying to restore deleted comments made by administrators', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'user_id' => $this->admin->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($modOwner, $comment))->toBeFalse();
    });

    it('returns false for mod owners trying to restore deleted comments made by moderators', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create([
            'user_id' => $this->moderator->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($modOwner, $comment))->toBeFalse();
    });

    it('returns false for profile owners trying to restore deleted comments made by administrators', function (): void {
        $profileOwner = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $this->admin->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($profileOwner, $comment))->toBeFalse();
    });

    it('returns false for profile owners trying to restore deleted comments made by moderators', function (): void {
        $profileOwner = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $this->moderator->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($profileOwner, $comment))->toBeFalse();
    });
});

describe('viewActions Policy Method', function (): void {
    it('returns false for guests', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->viewActions(null, $comment))->toBeFalse();
    });

    it('returns false for users with unverified email', function (): void {
        $unverifiedUser = User::factory()->unverified()->create();
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->viewActions($unverifiedUser, $comment))->toBeFalse();
    });

    it('returns true for moderators', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->viewActions($this->moderator, $comment))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->viewActions($this->admin, $comment))->toBeTrue();
    });

    it('returns false for regular users who are not mod owners or authors', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create();

        expect($this->policy->viewActions($this->user, $comment))->toBeFalse();
    });

    it('returns true for mod owners', function (): void {
        $modOwner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->viewActions($modOwner, $comment))->toBeTrue();
    });

    it('returns true for mod authors', function (): void {
        $modAuthor = User::factory()->create();
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($modAuthor);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->viewActions($modAuthor, $comment))->toBeTrue();
    });

    it('returns false for mod owners on different mods', function (): void {
        $modOwner = User::factory()->create();
        $mod1 = Mod::factory()->create(['owner_id' => $modOwner->id]);
        $mod2 = Mod::factory()->create();
        $comment = Comment::factory()->for($mod2, 'commentable')->create();

        expect($this->policy->viewActions($modOwner, $comment))->toBeFalse();
    });

    it('returns true for profile owners on their own profile comments', function (): void {
        $profileOwner = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $commenter->id,
        ]);

        expect($this->policy->viewActions($profileOwner, $comment))->toBeTrue();
    });

    it('returns false for users on other users profile comments', function (): void {
        $profileOwner = User::factory()->create();
        $otherUser = User::factory()->create();
        $commenter = User::factory()->create();

        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $profileOwner->id,
            'user_id' => $commenter->id,
        ]);

        expect($this->policy->viewActions($otherUser, $comment))->toBeFalse();
    });

    it('returns true for administrators who own the mod', function (): void {
        $mod = Mod::factory()->create(['owner_id' => $this->admin->id]);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->viewActions($this->admin, $comment))->toBeTrue();
    });

    it('returns true for moderators who are mod authors', function (): void {
        $mod = Mod::factory()->create();
        $mod->additionalAuthors()->attach($this->moderator);
        $comment = Comment::factory()->for($mod, 'commentable')->create();

        expect($this->policy->viewActions($this->moderator, $comment))->toBeTrue();
    });

    it('returns true for administrators who own the profile being commented on', function (): void {
        $comment = Comment::factory()->create([
            'commentable_type' => User::class,
            'commentable_id' => $this->admin->id,
            'user_id' => $this->user->id,
        ]);

        expect($this->policy->viewActions($this->admin, $comment))->toBeTrue();
    });

    it('returns true for addon owners', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create();

        expect($this->policy->viewActions($addonOwner, $comment))->toBeTrue();
    });

    it('returns true for addon authors', function (): void {
        $addonAuthor = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($addonAuthor);
        $comment = Comment::factory()->for($addon, 'commentable')->create();

        expect($this->policy->viewActions($addonAuthor, $comment))->toBeTrue();
    });

    it('returns false for addon owners on different addons', function (): void {
        $addonOwner = User::factory()->create();
        $addon1 = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $addon2 = Addon::factory()->create();
        $comment = Comment::factory()->for($addon2, 'commentable')->create();

        expect($this->policy->viewActions($addonOwner, $comment))->toBeFalse();
    });
});

describe('modOwnerSoftDelete Policy Method for Addons', function (): void {
    it('returns true for addon owners', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($addonOwner, $comment))->toBeTrue();
    });

    it('returns true for addon authors', function (): void {
        $addonAuthor = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($addonAuthor);
        $comment = Comment::factory()->for($addon, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($addonAuthor, $comment))->toBeTrue();
    });

    it('returns false for addon owners on different addons', function (): void {
        $addonOwner = User::factory()->create();
        $addon1 = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $addon2 = Addon::factory()->create();
        $comment = Comment::factory()->for($addon2, 'commentable')->create();

        expect($this->policy->modOwnerSoftDelete($addonOwner, $comment))->toBeFalse();
    });

    it('returns false for addon owners trying to delete comments made by administrators', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create([
            'user_id' => $this->admin->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($addonOwner, $comment))->toBeFalse();
    });

    it('returns false for addon owners trying to delete comments made by moderators', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create([
            'user_id' => $this->moderator->id,
        ]);

        expect($this->policy->modOwnerSoftDelete($addonOwner, $comment))->toBeFalse();
    });

    it('returns false for already deleted addon comments', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create([
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerSoftDelete($addonOwner, $comment))->toBeFalse();
    });
});

describe('modOwnerRestore Policy Method for Addons', function (): void {
    it('returns true for addon owners on deleted addon comments', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create([
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($addonOwner, $comment))->toBeTrue();
    });

    it('returns true for addon authors on deleted addon comments', function (): void {
        $addonAuthor = User::factory()->create();
        $addon = Addon::factory()->create();
        $addon->additionalAuthors()->attach($addonAuthor);
        $comment = Comment::factory()->for($addon, 'commentable')->create([
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($addonAuthor, $comment))->toBeTrue();
    });

    it('returns false for addon owners on non-deleted addon comments', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create();

        expect($this->policy->modOwnerRestore($addonOwner, $comment))->toBeFalse();
    });

    it('returns false for addon owners trying to restore deleted comments made by administrators', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create([
            'user_id' => $this->admin->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($addonOwner, $comment))->toBeFalse();
    });

    it('returns false for addon owners trying to restore deleted comments made by moderators', function (): void {
        $addonOwner = User::factory()->create();
        $addon = Addon::factory()->create(['owner_id' => $addonOwner->id]);
        $comment = Comment::factory()->for($addon, 'commentable')->create([
            'user_id' => $this->moderator->id,
            'deleted_at' => now(),
        ]);

        expect($this->policy->modOwnerRestore($addonOwner, $comment))->toBeFalse();
    });
});

describe('update Policy Method', function (): void {
    it('allows the author to edit a clean comment', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        expect($this->policy->update($this->user, $comment))->toBeTrue();
    });

    it('allows the author to edit a pending comment', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
            'spam_status' => SpamStatus::PENDING,
        ]);

        expect($this->policy->update($this->user, $comment))->toBeTrue();
    });

    it('blocks the author from editing a spam-flagged comment', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
            'spam_status' => SpamStatus::SPAM,
        ]);

        expect($this->policy->update($this->user, $comment))->toBeFalse();
    });

    it('blocks non-authors from editing', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        expect($this->policy->update($this->otherUser, $comment))->toBeFalse();
    });
});

describe('report Policy Method', function (): void {
    it('returns false for the comment author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
        ]);

        expect($this->policy->report($this->user, $comment))->toBeFalse();
    });

    it('returns true for a user who is not the comment author', function (): void {
        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
        ]);

        expect($this->policy->report($this->user, $comment))->toBeTrue();
    });
});

describe('checkForSpam Policy Method', function (): void {
    it('returns false when Akismet is disabled even for moderators', function (): void {
        Config::set('akismet.enabled', false);

        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        expect($this->policy->checkForSpam($this->moderator, $comment))->toBeFalse();
        expect($this->policy->checkForSpam($this->admin, $comment))->toBeFalse();
    });

    it('returns true for moderators when Akismet is enabled and recheck attempts remain', function (): void {
        Config::set('akismet.enabled', true);

        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
            'spam_status' => SpamStatus::CLEAN,
            'spam_recheck_count' => 0,
        ]);

        expect($this->policy->checkForSpam($this->moderator, $comment))->toBeTrue();
        expect($this->policy->checkForSpam($this->admin, $comment))->toBeTrue();
    });

    it('returns false for regular users even when Akismet is enabled', function (): void {
        Config::set('akismet.enabled', true);

        $comment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
            'spam_status' => SpamStatus::CLEAN,
        ]);

        expect($this->policy->checkForSpam($this->user, $comment))->toBeFalse();
    });
});

describe('pin authorization', function (): void {
    it('allows mod owners, mod authors, moderators, and admins to pin comments', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();
        $mod->additionalAuthors()->attach($author);

        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        expect($owner->can('pin', $comment))->toBeTrue();
        expect($author->can('pin', $comment))->toBeTrue();
        expect($moderator->can('pin', $comment))->toBeTrue();
        expect($admin->can('pin', $comment))->toBeTrue();
    });

    it('prevents regular users from pinning comments', function (): void {
        $user = User::factory()->create();
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        expect($user->can('pin', $comment))->toBeFalse();
    });

    it('prevents pinning reply comments', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $rootComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        $replyComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'parent_id' => $rootComment->id,
            'root_id' => $rootComment->id,
        ]);

        $moderator = User::factory()->moderator()->create();

        // Root comment should be pinnable
        expect($owner->can('pin', $rootComment))->toBeTrue();
        expect($moderator->can('pin', $rootComment))->toBeTrue();

        // Reply comment should not be pinnable
        expect($owner->can('pin', $replyComment))->toBeFalse();
        expect($moderator->can('pin', $replyComment))->toBeFalse();

        // Reply comment should not show the owner pin action
        expect($owner->can('showOwnerPinAction', $replyComment))->toBeFalse();
    });

    it('allows mod owners, authors, moderators, and admins to pin (unpin) soft-deleted comments', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();
        $mod->additionalAuthors()->attach($author);

        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now(),
            'deleted_at' => now(),
        ]);

        expect($comment->isPinned())->toBeTrue();
        expect($comment->isDeleted())->toBeTrue();

        // All privileged users should still be able to unpin a soft-deleted comment
        expect($owner->can('pin', $comment))->toBeTrue();
        expect($author->can('pin', $comment))->toBeTrue();
        expect($moderator->can('pin', $comment))->toBeTrue();
        expect($admin->can('pin', $comment))->toBeTrue();
    });

    it('grants pin permission on a deleted comment so it can be unpinned', function (): void {
        $owner = User::factory()->create();
        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'deleted_at' => now(),
        ]);

        // Comment is deleted but not pinned; owner retains pin permission (used for unpinning in the UI).
        expect($comment->isDeleted())->toBeTrue();
        expect($comment->isPinned())->toBeFalse();
        expect($owner->can('pin', $comment))->toBeTrue();
    });

    it('shows owner pin actions only to mod owners and authors', function (): void {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $regularUser = User::factory()->create();
        $moderator = User::factory()->moderator()->create();

        $mod = Mod::factory()->create(['owner_id' => $owner->id]);
        ModVersion::factory()->recycle($mod)->create();
        $mod->additionalAuthors()->attach($author);

        $comment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
        ]);

        // Mod owner and author should see the owner pin action
        expect($owner->can('showOwnerPinAction', $comment))->toBeTrue();
        expect($author->can('showOwnerPinAction', $comment))->toBeTrue();

        // Regular user should not see the owner pin action
        expect($regularUser->can('showOwnerPinAction', $comment))->toBeFalse();

        // Moderator should not see the owner pin action (they use the moderation dropdown)
        expect($moderator->can('showOwnerPinAction', $comment))->toBeFalse();
    });
});

describe('pin ordering', function (): void {
    it('displays pinned comments first then newest unpinned', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        // Create comments with different timestamps
        $oldComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDays(3),
        ]);

        $newComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDay(),
        ]);

        $pinnedComment = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'created_at' => now()->subDays(2),
            'pinned_at' => now()->subHour(),
        ]);

        // Get root comments with ordering (rootComments() includes pinned ordering)
        $comments = $mod->rootComments()->get();

        // Pinned comment should be first (non-null pinned_at should come first)
        expect($comments->first()->id)->toBe($pinnedComment->id);

        // Then newest unpinned comment, then oldest unpinned comment
        expect($comments->get(1)->id)->toBe($newComment->id);
        expect($comments->get(2)->id)->toBe($oldComment->id);
    });

    it('orders multiple pinned comments by pin time', function (): void {
        $mod = Mod::factory()->create();
        ModVersion::factory()->recycle($mod)->create();

        // Create pinned comments with different pin times
        $firstPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHours(3),
        ]);

        $secondPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHours(2),
        ]);

        $latestPinned = Comment::factory()->create([
            'commentable_id' => $mod->id,
            'commentable_type' => Mod::class,
            'pinned_at' => now()->subHour(),
        ]);

        // Get root comments with ordering (rootComments() includes pinned ordering)
        $comments = $mod->rootComments()->get();

        // Latest pinned should be first
        expect($comments->get(0)->id)->toBe($latestPinned->id);
        expect($comments->get(1)->id)->toBe($secondPinned->id);
        expect($comments->get(2)->id)->toBe($firstPinned->id);
    });
});

describe('create Policy Method reply blocking', function (): void {
    it('allows replying when there is no block relationship with the parent author', function (): void {
        $parentComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
        ]);

        expect($this->policy->create($this->user, $this->mod, $parentComment))->toBeTrue();
    });

    it('denies replying when the replier has blocked the parent comment author', function (): void {
        $parentComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
        ]);

        $this->user->block($this->otherUser);

        expect($this->policy->create($this->user, $this->mod, $parentComment))->toBeFalse();
    });

    it('denies replying when the parent comment author has blocked the replier', function (): void {
        $parentComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->otherUser->id,
        ]);

        $this->otherUser->block($this->user);

        expect($this->policy->create($this->user, $this->mod, $parentComment))->toBeFalse();
    });

    it('allows replying to your own comment regardless of other block relationships', function (): void {
        $parentComment = Comment::factory()->for($this->mod, 'commentable')->create([
            'user_id' => $this->user->id,
        ]);

        $this->user->block($this->otherUser);

        expect($this->policy->create($this->user, $this->mod, $parentComment))->toBeTrue();
    });

    it('ignores block relationships for root comments without a parent', function (): void {
        $this->user->block($this->otherUser);

        expect($this->policy->create($this->user, $this->mod))->toBeTrue();
    });
});
