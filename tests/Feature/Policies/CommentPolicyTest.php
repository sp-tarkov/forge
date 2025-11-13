<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake(); // Prevent spam check jobs from running

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create moderator with proper role
    $this->moderator = User::factory()->moderator()->create();

    // Create admin with proper role
    $this->admin = User::factory()->admin()->create();

    $this->mod = Mod::factory()->create();
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
