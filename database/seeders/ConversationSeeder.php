<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use DateTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class ConversationSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();
        $counts = $this->getDefaultCounts();

        /** @var list<int> $userIds */
        $userIds = User::query()->pluck('id')->all();
        $testAccount = User::query()->where('email', 'test@example.com')->first();

        if (count($userIds) < 2) {
            return;
        }

        $this->seedConversations($counts, $userIds, $testAccount?->id);

        if ($testAccount) {
            $this->seedMarkdownConversation($testAccount);
        }
    }

    /**
     * Bulk-create conversations with messages, read receipts, archives, and last-message pointers.
     *
     * @param  array<string, mixed>  $counts
     * @param  non-empty-list<int>  $userIds
     */
    private function seedConversations(array $counts, array $userIds, ?int $testAccountId): void
    {
        /** @var int $conversationCount */
        $conversationCount = $counts['conversations'];
        /** @var array{0: int, 1: int} $messageRange */
        $messageRange = $counts['messagesPerConversation'];

        $conversationRows = [];
        $participants = [];
        $seenPairs = [];

        for ($i = 0; $i < $conversationCount; $i++) {
            [$userA, $userB] = $this->selectParticipants($userIds, $testAccountId);
            $user1Id = min($userA, $userB);
            $user2Id = max($userA, $userB);

            $pairKey = $user1Id.'-'.$user2Id;
            if (isset($seenPairs[$pairKey])) {
                continue;
            }

            $seenPairs[$pairKey] = true;
            $createdAt = $this->faker->dateTimeBetween('-3 months', 'now');

            $conversationRows[] = [
                'hash_id' => null,
                'user1_id' => $user1Id,
                'user2_id' => $user2Id,
                'created_by' => $this->randomElement([$user1Id, $user2Id]),
                'last_message_at' => null,
                'last_message_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
            $participants[] = [$user1Id, $user2Id, $createdAt];
        }

        $conversationIds = $this->bulkInsertReturningIds('conversations', $conversationRows);

        [$messageRows, $recipientIds, $lastMessageIndexes] = $this->buildMessages($conversationIds, $participants, $messageRange);
        $messageIds = $this->bulkInsertReturningIds('messages', $messageRows, 1000);

        $this->seedMessageReads($messageIds, $messageRows, $recipientIds);
        $this->updateConversationPointers($conversationIds, $messageIds, $messageRows, $lastMessageIndexes);
        $this->seedConversationArchives($conversationIds, $participants, $messageRows, $lastMessageIndexes);
    }

    /**
     * Select two distinct participant IDs, including the test account roughly 30% of the time.
     *
     * @param  non-empty-list<int>  $userIds
     * @return array{0: int, 1: int}
     */
    private function selectParticipants(array $userIds, ?int $testAccountId): array
    {
        if ($testAccountId !== null && random_int(0, 9) < 3) {
            do {
                $otherId = $this->randomElement($userIds);
            } while ($otherId === $testAccountId);

            return [$testAccountId, $otherId];
        }

        [$userA, $userB] = $this->randomElements($userIds, 2);

        return [$userA, $userB];
    }

    /**
     * Build message rows for every conversation with chronological timestamps and alternating senders.
     *
     * @param  list<int>  $conversationIds
     * @param  list<array{int, int, DateTime}>  $participants
     * @param  array{0: int, 1: int}  $messageRange
     * @return array{0: list<array<string, mixed>>, 1: list<int>, 2: array<int, int>}
     */
    private function buildMessages(array $conversationIds, array $participants, array $messageRange): array
    {
        $rows = [];
        $recipientIds = [];
        $lastMessageIndexes = [];

        foreach ($conversationIds as $index => $conversationId) {
            [$user1Id, $user2Id, $conversationCreatedAt] = $participants[$index];

            $messageCount = random_int($messageRange[0], $messageRange[1]);
            $timestamps = [];
            for ($i = 0; $i < $messageCount; $i++) {
                $timestamps[] = $this->faker->dateTimeBetween($conversationCreatedAt, 'now');
            }

            sort($timestamps);

            foreach ($timestamps as $i => $timestamp) {
                $senderId = $this->selectMessageSender($i, $user1Id, $user2Id);
                $rows[] = [
                    'conversation_id' => $conversationId,
                    'user_id' => $senderId,
                    'content' => $this->generateMessageContent(),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $recipientIds[] = $senderId === $user1Id ? $user2Id : $user1Id;
            }

            $lastMessageIndexes[$index] = count($rows) - 1;
        }

        return [$rows, $recipientIds, $lastMessageIndexes];
    }

    /**
     * Select the sender for a message.
     */
    private function selectMessageSender(int $index, int $user1Id, int $user2Id): int
    {
        // Every third message, randomly select the sender
        if ($index % 3 === 0) {
            return $this->randomElement([$user1Id, $user2Id]);
        }

        // Otherwise, alternate between users
        return $index % 2 === 0 ? $user1Id : $user2Id;
    }

    /**
     * Generate message content from a mix of faker text and canned phrases.
     */
    private function generateMessageContent(): string
    {
        $variant = random_int(0, 12);

        return match ($variant) {
            0 => $this->faker->sentence(),
            1 => $this->faker->paragraph(2),
            2 => $this->faker->text(100),
            default => $this->randomElement([
                'Hey, how are you doing?',
                'Did you see the latest update?',
                'Thanks for your help!',
                "Let me know when you're available.",
                "I'll check that out, thanks!",
                'Sounds good to me!',
                'Can we discuss this tomorrow?',
                'I agree with your approach.',
                "That's a great idea!",
                "I'm working on it now.",
            ]),
        };
    }

    /**
     * Bulk-create read receipts for roughly 70% of the messages.
     *
     * @param  list<int>  $messageIds
     * @param  list<array<string, mixed>>  $messageRows
     * @param  list<int>  $recipientIds
     */
    private function seedMessageReads(array $messageIds, array $messageRows, array $recipientIds): void
    {
        $rows = [];
        foreach ($messageIds as $index => $messageId) {
            if (random_int(0, 9) >= 7) {
                continue;
            }

            /** @var DateTime $sentAt */
            $sentAt = $messageRows[$index]['created_at'];

            $rows[] = [
                'message_id' => $messageId,
                'user_id' => $recipientIds[$index],
                'read_at' => $this->faker->dateTimeBetween($sentAt, 'now'),
            ];
        }

        $this->bulkInsert('message_reads', $rows, 1000);
    }

    /**
     * Set each conversation's hash ID and last message pointer.
     *
     * @param  list<int>  $conversationIds
     * @param  list<int>  $messageIds
     * @param  list<array<string, mixed>>  $messageRows
     * @param  array<int, int>  $lastMessageIndexes
     */
    private function updateConversationPointers(array $conversationIds, array $messageIds, array $messageRows, array $lastMessageIndexes): void
    {
        foreach ($conversationIds as $index => $conversationId) {
            $lastMessageIndex = $lastMessageIndexes[$index];

            DB::table('conversations')->where('id', $conversationId)->update([
                'hash_id' => Conversation::generateHashId($conversationId),
                'last_message_id' => $messageIds[$lastMessageIndex],
                'last_message_at' => $messageRows[$lastMessageIndex]['created_at'],
            ]);
        }
    }

    /**
     * Bulk-create an archive record for roughly 20% of the conversations.
     *
     * @param  list<int>  $conversationIds
     * @param  list<array{int, int, DateTime}>  $participants
     * @param  list<array<string, mixed>>  $messageRows
     * @param  array<int, int>  $lastMessageIndexes
     */
    private function seedConversationArchives(array $conversationIds, array $participants, array $messageRows, array $lastMessageIndexes): void
    {
        $now = Date::now();

        $rows = [];
        foreach ($conversationIds as $index => $conversationId) {
            if (random_int(0, 9) >= 2) {
                continue;
            }

            [$user1Id, $user2Id] = $participants[$index];
            /** @var DateTime $lastMessageAt */
            $lastMessageAt = $messageRows[$lastMessageIndexes[$index]]['created_at'];

            $rows[] = [
                'conversation_id' => $conversationId,
                'user_id' => $this->randomElement([$user1Id, $user2Id]),
                'archived_at' => $this->faker->dateTimeBetween($lastMessageAt, 'now'),
                'reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkInsert('conversation_archives', $rows);
    }

    /**
     * Seed a conversation with markdown content for testing.
     */
    private function seedMarkdownConversation(User $testAccount): void
    {
        $randomUser = User::query()->where('id', '!=', $testAccount->id)->inRandomOrder()->first();
        if (! $randomUser) {
            return;
        }

        // Ensure consistent ordering
        $userId1 = min($testAccount->id, $randomUser->id);
        $userId2 = max($testAccount->id, $randomUser->id);

        // Check if conversation already exists
        $conversation = Conversation::query()->where('user1_id', $userId1)
            ->where('user2_id', $userId2)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::factory()->create([
                'user1_id' => $userId1,
                'user2_id' => $userId2,
                'created_by' => $randomUser->id,
                'created_at' => now()->subDays(2),
            ]);

            // Set the hash_id when it is missing
            if (! $conversation->hash_id) {
                $conversation->hash_id = Conversation::generateHashId($conversation->id);
                $conversation->saveQuietly();
            }
        }

        // Load markdown content
        $markdownPath = database_path('../resources/markdown/exampleChatMessage.md');
        if (! file_exists($markdownPath)) {
            return;
        }

        $markdownContent = file_get_contents($markdownPath);

        // Create initial message from random user with markdown content
        /** @var Message $markdownMessage */
        $markdownMessage = Message::withoutEvents(fn () => Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $randomUser->id,
            'content' => $markdownContent,
            'created_at' => now()->subHours(3),
        ]));

        // Test account reads the message
        MessageRead::factory()->create([
            'message_id' => $markdownMessage->id,
            'user_id' => $testAccount->id,
            'read_at' => now()->subHours(2),
        ]);

        // Test account responds
        /** @var Message $response */
        $response = Message::withoutEvents(fn () => Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $testAccount->id,
            'content' => "Thanks for sharing this! The **markdown formatting** looks great.\n\nI especially like:\n- The code examples\n- The organized structure\n- The helpful links\n\nI'll give it a try and let you know how it goes!",
            'created_at' => now()->subHours(2),
        ]));

        // Random user reads the response
        MessageRead::factory()->create([
            'message_id' => $response->id,
            'user_id' => $randomUser->id,
            'read_at' => now()->subHour(),
        ]);

        // Random user sends another message
        /** @var Message $finalMessage */
        $finalMessage = Message::withoutEvents(fn () => Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $randomUser->id,
            'content' => "You're welcome! Let me know if you need any help with the `configuration settings` or if you run into any ~~problems~~ issues.",
            'created_at' => now()->subMinutes(30),
        ]));

        // Update conversation's last message fields
        $conversation->update([
            'last_message_id' => $finalMessage->id,
            'last_message_at' => $finalMessage->created_at,
        ]);
    }
}
