<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;
use App\Models\UserRole;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake(); // Prevent spam check jobs from running

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    // Create moderator with proper role
    $moderatorRole = UserRole::factory()->moderator()->create();
    $this->moderator = User::factory()->create();
    $this->moderator->assignRole($moderatorRole);

    // Create admin with proper role
    $adminRole = UserRole::factory()->administrator()->create();
    $this->admin = User::factory()->create();
    $this->admin->assignRole($adminRole);

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
