<?php

declare(strict_types=1);

use App\Models\OAuthConnection;
use App\Models\User;
use App\Policies\OAuthConnectionPolicy;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->policy = new OAuthConnectionPolicy;
});

describe('view Policy Method', function (): void {
    it('returns true when user owns the connection', function (): void {
        $connection = OAuthConnection::factory()->create(['user_id' => $this->user->id]);

        expect($this->policy->view($this->user, $connection))->toBeTrue();
    });

    it('returns false when user does not own the connection', function (): void {
        $connection = OAuthConnection::factory()->create(['user_id' => $this->otherUser->id]);

        expect($this->policy->view($this->user, $connection))->toBeFalse();
    });
});

describe('delete Policy Method', function (): void {
    it('returns true when user owns the connection and has a password', function (): void {
        $connection = OAuthConnection::factory()->create(['user_id' => $this->user->id]);

        expect($this->policy->delete($this->user, $connection))->toBeTrue();
    });

    it('returns false when user owns the connection but has no password', function (): void {
        $userWithoutPassword = User::factory()->create(['password' => null]);
        $connection = OAuthConnection::factory()->create(['user_id' => $userWithoutPassword->id]);

        expect($this->policy->delete($userWithoutPassword, $connection))->toBeFalse();
    });

    it('returns false when user does not own the connection', function (): void {
        $connection = OAuthConnection::factory()->create(['user_id' => $this->otherUser->id]);

        expect($this->policy->delete($this->user, $connection))->toBeFalse();
    });

    it('returns false when user does not own the connection and has no password', function (): void {
        $userWithoutPassword = User::factory()->create(['password' => null]);
        $connection = OAuthConnection::factory()->create(['user_id' => $this->otherUser->id]);

        expect($this->policy->delete($userWithoutPassword, $connection))->toBeFalse();
    });
});
