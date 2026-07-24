<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Livewire\Livewire;

describe('starting conversations', function (): void {
    beforeEach(function (): void {
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->actingAs($this->userA);
    });

    it('starts a new conversation with an unblocked user', function (): void {
        Livewire::test('navigation-chat')
            ->call('startConversation', $this->userB->id)
            ->assertRedirect();

        expect(Conversation::query()->count())->toBe(1);
    });

    it('prevents starting a new conversation with a user who has blocked you', function (): void {
        $this->userB->block($this->userA);

        Livewire::test('navigation-chat')
            ->call('startConversation', $this->userB->id)
            ->assertNoRedirect();

        expect(Conversation::query()->count())->toBe(0);
    });

    it('prevents starting a new conversation with a user you have blocked', function (): void {
        $this->userA->block($this->userB);

        Livewire::test('navigation-chat')
            ->call('startConversation', $this->userB->id)
            ->assertNoRedirect();

        expect(Conversation::query()->count())->toBe(0);
    });

    it('reopens an existing conversation with a user you have blocked', function (): void {
        $conversation = Conversation::factory()->create([
            'user1_id' => $this->userA->id,
            'user2_id' => $this->userB->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->userA->id,
            'content' => 'test message',
        ]);

        $this->userA->block($this->userB);

        Livewire::test('navigation-chat')
            ->call('startConversation', $this->userB->id)
            ->assertRedirect($conversation->url);

        expect(Conversation::query()->count())->toBe(1);
    });

    it('prevents unarchiving a conversation with a user who has blocked you', function (): void {
        $conversation = Conversation::factory()->create([
            'user1_id' => $this->userA->id,
            'user2_id' => $this->userB->id,
        ]);
        $conversation->archiveFor($this->userA);

        $this->userB->block($this->userA);

        Livewire::test('navigation-chat')
            ->call('startConversation', $this->userB->id)
            ->assertNoRedirect();

        expect($conversation->fresh()->isArchivedBy($this->userA))->toBeTrue();
    });

    it('unarchives an archived conversation with a user you have blocked', function (): void {
        $conversation = Conversation::factory()->create([
            'user1_id' => $this->userA->id,
            'user2_id' => $this->userB->id,
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $this->userA->id,
            'content' => 'test message',
        ]);
        $conversation->archiveFor($this->userA);

        $this->userA->block($this->userB);

        Livewire::test('navigation-chat')
            ->call('startConversation', $this->userB->id)
            ->assertRedirect($conversation->url);

        expect($conversation->fresh()->isArchivedBy($this->userA))->toBeFalse();
    });
});
