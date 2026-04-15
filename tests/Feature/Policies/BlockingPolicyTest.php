<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserBlock;
use App\Policies\BlockingPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->target = User::factory()->create();
    $this->moderator = User::factory()->moderator()->create();
    $this->seniorModerator = User::factory()->seniorModerator()->create();
    $this->admin = User::factory()->admin()->create();
    $this->policy = new BlockingPolicy;
});

describe('block', function (): void {
    it('denies blocking yourself', function (): void {
        $result = $this->policy->block($this->user, $this->user);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->denied())->toBeTrue();
    });

    it('denies blocking an already blocked user', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->target->id,
        ]);

        $result = $this->policy->block($this->user, $this->target);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->denied())->toBeTrue();
    });

    it('denies blocking an admin', function (): void {
        $result = $this->policy->block($this->user, $this->admin);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->denied())->toBeTrue();
    });

    it('denies blocking a senior moderator', function (): void {
        $result = $this->policy->block($this->user, $this->seniorModerator);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->denied())->toBeTrue();
    });

    it('denies blocking a moderator', function (): void {
        $result = $this->policy->block($this->user, $this->moderator);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->denied())->toBeTrue();
    });

    it('allows blocking a regular user', function (): void {
        $result = $this->policy->block($this->user, $this->target);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->allowed())->toBeTrue();
    });
});

describe('unblock', function (): void {
    it('denies unblocking a user who is not blocked', function (): void {
        $result = $this->policy->unblock($this->user, $this->target);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->denied())->toBeTrue();
    });

    it('allows unblocking a user who is blocked', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->target->id,
        ]);

        $result = $this->policy->unblock($this->user, $this->target);

        expect($result)->toBeInstanceOf(Response::class)
            ->and($result->allowed())->toBeTrue();
    });
});

describe('viewBlockedUsers', function (): void {
    it('always returns true', function (): void {
        expect($this->policy->viewBlockedUsers($this->user))->toBeTrue();
    });

    it('returns true for admins', function (): void {
        expect($this->policy->viewBlockedUsers($this->admin))->toBeTrue();
    });
});

describe('canInteract', function (): void {
    it('returns true when no blocking exists', function (): void {
        expect($this->policy->canInteract($this->user, $this->target))->toBeTrue();
    });

    it('returns false when user has blocked target', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->target->id,
        ]);

        expect($this->policy->canInteract($this->user, $this->target))->toBeFalse();
    });

    it('returns false when target has blocked user', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->target->id,
            'blocked_id' => $this->user->id,
        ]);

        expect($this->policy->canInteract($this->user, $this->target))->toBeFalse();
    });
});

describe('viewProfile', function (): void {
    it('returns true when no blocking exists', function (): void {
        expect($this->policy->viewProfile($this->user, $this->target))->toBeTrue();
    });

    it('returns false when user has blocked target', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->target->id,
        ]);

        expect($this->policy->viewProfile($this->user, $this->target))->toBeFalse();
    });

    it('returns false when target has blocked user', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->target->id,
            'blocked_id' => $this->user->id,
        ]);

        expect($this->policy->viewProfile($this->user, $this->target))->toBeFalse();
    });
});

describe('sendMessage', function (): void {
    it('returns true when no blocking exists', function (): void {
        expect($this->policy->sendMessage($this->user, $this->target))->toBeTrue();
    });

    it('returns false when mutual blocking exists', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->target->id,
        ]);

        expect($this->policy->sendMessage($this->user, $this->target))->toBeFalse();
    });
});

describe('commentOnContent', function (): void {
    it('returns true when no blocking exists', function (): void {
        expect($this->policy->commentOnContent($this->user, $this->target))->toBeTrue();
    });

    it('returns false when user has blocked content owner', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->user->id,
            'blocked_id' => $this->target->id,
        ]);

        expect($this->policy->commentOnContent($this->user, $this->target))->toBeFalse();
    });

    it('returns false when content owner has blocked user', function (): void {
        UserBlock::factory()->create([
            'blocker_id' => $this->target->id,
            'blocked_id' => $this->user->id,
        ]);

        expect($this->policy->commentOnContent($this->user, $this->target))->toBeFalse();
    });
});
