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
