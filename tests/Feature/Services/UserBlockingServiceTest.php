<?php

declare(strict_types=1);

use App\Jobs\CleanupBlockedNotificationsJob;
use App\Models\Conversation;
use App\Models\ConversationArchive;
use App\Models\User;
use App\Services\UserBlockingService;
use Illuminate\Support\Facades\Queue;

describe('blockUser', function (): void {
    it('does not automatically archive conversations when blocking', function (): void {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create conversation with correct structure.
        $conversation = Conversation::findOrCreateBetween($userA, $userB, $userA);

        $service = new UserBlockingService;
        $service->blockUser($userA, $userB);

        // Conversations are no longer automatically archived when blocking.
        expect(ConversationArchive::query()->where('conversation_id', $conversation->id)->count())
            ->toBe(0);
    });

    it('dispatches the blocked notification cleanup job', function (): void {
        Queue::fake();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $service = new UserBlockingService;
        $service->blockUser($userA, $userB);

        Queue::assertPushed(
            CleanupBlockedNotificationsJob::class,
            fn (CleanupBlockedNotificationsJob $job): bool => $job->blocker->is($userA) && $job->blocked->is($userB)
        );
    });
});

describe('unblockUser', function (): void {
    it('keeps messages blocked while the other user still has an active block', function (): void {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::findOrCreateBetween($userA, $userB, $userA);

        $service = new UserBlockingService;
        $service->blockUser($userA, $userB);
        $service->blockUser($userB, $userA);

        // Both users block each other - cannot send messages.
        $this->actingAs($userA);
        expect($userA->can('sendMessage', $conversation))->toBeFalse();

        // UserA unblocks UserB.
        $service->unblockUser($userA, $userB);

        // Still cannot send messages since userB still blocks userA.
        $this->actingAs($userA);
        expect($userA->can('sendMessage', $conversation))->toBeFalse();

        $this->actingAs($userB);
        expect($userB->can('sendMessage', $conversation))->toBeFalse();

        // UserB also unblocks UserA.
        $service->unblockUser($userB, $userA);

        // Now messages can be sent.
        $this->actingAs($userA);
        expect($userA->can('sendMessage', $conversation))->toBeTrue();

        $this->actingAs($userB);
        expect($userB->can('sendMessage', $conversation))->toBeTrue();
    });
});
