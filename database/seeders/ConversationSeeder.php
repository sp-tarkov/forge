<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\ConversationArchive;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Database\Seeders\Traits\SeederHelpers;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

use function Laravel\Prompts\progress;

class ConversationSeeder extends Seeder
{
    use SeederHelpers;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->initializeFaker();
        $counts = $this->getDefaultCounts();

        $allUsers = User::all();
        $testAccount = User::where('email', 'test@example.com')->first();

        // Seed regular conversations
        $this->seedConversations($counts, $allUsers, $testAccount);

        // Seed markdown test conversation
        if ($testAccount) {
            $this->seedMarkdownConversation($testAccount, $allUsers);
        }
    }

    /**
     * Seed conversations and messages.
     *
     * @param  array<string, mixed>  $counts
     * @param  Collection<int, User>  $allUsers
     */
    private function seedConversations(array $counts, Collection $allUsers, ?User $testAccount): void
    {
        progress(
            label: 'Adding Conversations and Messages...',
            steps: $counts['conversations'],
            callback: function (int $step) use ($allUsers, $testAccount, $counts) {
                $this->createConversationWithMessages($allUsers, $testAccount, $counts);
            }
        );
    }

    /**
     * Create a conversation with messages.
     *
     * @param  Collection<int, User>  $allUsers
     * @param  array<string, mixed>  $counts
     */
    private function createConversationWithMessages(Collection $allUsers, ?User $testAccount, array $counts): void
    {
        // Select conversation participants
        [$user1, $user2] = $this->selectConversationParticipants($allUsers, $testAccount);

        // Ensure consistent ordering for user1_id and user2_id
        $userId1 = min($user1->id, $user2->id);
        $userId2 = max($user1->id, $user2->id);

        // Check if conversation already exists
        if ($this->conversationExists($userId1, $userId2)) {
            return;
        }

        // Create the conversation
        $conversation = $this->createConversation($userId1, $userId2);

        // Create messages
        $messages = $this->createMessages($conversation, $user1, $user2, $counts);

        // Update conversation's last message fields (since we're using withoutEvents)
        if ($messages->isNotEmpty()) {
            $lastMessage = $messages->last();
            $conversation->update([
                'last_message_id' => $lastMessage->id,
                'last_message_at' => $lastMessage->created_at,
            ]);
        }

        // Maybe archive the conversation
        $this->maybeArchiveConversation($conversation, $user1, $user2, $messages);
    }

    /**
     * Select participants for a conversation.
     *
     * @param  Collection<int, User>  $allUsers
     * @return array{0: User, 1: User}
     */
    private function selectConversationParticipants(Collection $allUsers, ?User $testAccount): array
    {
        // 30% chance to include the test account in the conversation
        if ($testAccount && rand(0, 9) < 3) {
            $user1 = $testAccount;
            $user2 = $allUsers->where('id', '!=', $testAccount->id)->random();
        } else {
            // Select two different random users
            $selectedUsers = $allUsers->random(2);
            $user1 = $selectedUsers->first();
            $user2 = $selectedUsers->last();
        }

        return [$user1, $user2];
    }

    /**
     * Check if a conversation exists between two users.
     */
    private function conversationExists(int $userId1, int $userId2): bool
    {
        return Conversation::where('user1_id', $userId1)
            ->where('user2_id', $userId2)
            ->exists();
    }

    /**
     * Create a conversation.
     */
    private function createConversation(int $userId1, int $userId2): Conversation
    {
        return Conversation::factory()->create([
            'user1_id' => $userId1,
            'user2_id' => $userId2,
            'created_by' => $this->faker->randomElement([$userId1, $userId2]),
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    /**
     * Create messages for a conversation.
     *
     * @param  array<string, mixed>  $counts
     * @return Collection<int, Message>
     */
    private function createMessages(Conversation $conversation, User $user1, User $user2, array $counts): Collection
    {
        $messageCount = rand($counts['messagesPerConversation'][0], $counts['messagesPerConversation'][1]);
        $messages = collect();

        // Generate chronological timestamps
        $messageTimestamps = $this->generateMessageTimestamps($conversation, $messageCount);

        for ($i = 0; $i < $messageCount; $i++) {
            $sender = $this->selectMessageSender($i, $user1, $user2);
            $message = $this->createMessage($conversation, $sender, $messageTimestamps[$i]);
            $messages->push($message);

            // Maybe mark as read
            $this->maybeMarkMessageAsRead($message, $sender, $user1, $user2, $messageTimestamps[$i]);
        }

        return $messages;
    }

    /**
     * Generate chronological timestamps for messages.
     *
     * @return array<int, DateTimeImmutable>
     */
    private function generateMessageTimestamps(Conversation $conversation, int $messageCount): array
    {
        $messageTimestamps = [];
        $startTime = $conversation->created_at;
        $endTime = now();

        for ($i = 0; $i < $messageCount; $i++) {
            $messageTimestamps[] = DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween($startTime, $endTime));
        }

        sort($messageTimestamps);

        return $messageTimestamps;
    }

    /**
     * Select the sender for a message.
     */
    private function selectMessageSender(int $index, User $user1, User $user2): User
    {
        // Every third message, randomly select the sender
        if ($index % 3 === 0) {
            return $this->faker->randomElement([$user1, $user2]);
        }

        // Otherwise, alternate between users
        return ($index % 2 === 0) ? $user1 : $user2;
    }

    /**
     * Create a message.
     */
    private function createMessage(Conversation $conversation, User $sender, DateTimeImmutable $timestamp): Message
    {
        return Message::withoutEvents(function () use ($conversation, $sender, $timestamp) {
            return Message::factory()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $sender->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        });
    }

    /**
     * Maybe mark a message as read.
     */
    private function maybeMarkMessageAsRead(Message $message, User $sender, User $user1, User $user2, DateTimeImmutable $messageTime): void
    {
        // 70% chance the message is read by the other user
        if (rand(0, 9) < 7) {
            $recipient = ($sender->id === $user1->id) ? $user2 : $user1;

            // Read time should be after message time
            $readTime = $this->faker->dateTimeBetween($messageTime, 'now');

            MessageRead::factory()->create([
                'message_id' => $message->id,
                'user_id' => $recipient->id,
                'read_at' => $readTime,
            ]);
        }
    }

    /**
     * Maybe archive a conversation.
     *
     * @param  Collection<int, Message>  $messages
     */
    private function maybeArchiveConversation(Conversation $conversation, User $user1, User $user2, Collection $messages): void
    {
        // 20% chance the conversation is archived by one of the users
        if (rand(0, 9) < 2) {
            $archivingUser = $this->faker->randomElement([$user1, $user2]);
            $lastMessageAt = $messages->isNotEmpty() ? $messages->last()->created_at : $conversation->created_at;

            ConversationArchive::factory()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $archivingUser->id,
                'archived_at' => $this->faker->dateTimeBetween($lastMessageAt, 'now'),
            ]);
        }
    }

    /**
     * Seed a conversation with markdown content for testing.
     *
     * @param  Collection<int, User>  $allUsers
     */
    private function seedMarkdownConversation(User $testAccount, Collection $allUsers): void
    {
        // Select a random user to have the conversation with
        $randomUser = $allUsers->where('id', '!=', $testAccount->id)->random();

        // Ensure consistent ordering
        $userId1 = min($testAccount->id, $randomUser->id);
        $userId2 = max($testAccount->id, $randomUser->id);

        // Check if conversation already exists
        $conversation = Conversation::where('user1_id', $userId1)
            ->where('user2_id', $userId2)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::factory()->create([
                'user1_id' => $userId1,
                'user2_id' => $userId2,
                'created_by' => $randomUser->id,
                'created_at' => now()->subDays(2),
            ]);
        }

        // Load markdown content
        $markdownPath = database_path('../resources/markdown/exampleChatMessage.md');
        if (! file_exists($markdownPath)) {
            return;
        }

        $markdownContent = file_get_contents($markdownPath);

        // Create initial message from random user with markdown content
        $markdownMessage = Message::withoutEvents(function () use ($conversation, $randomUser, $markdownContent) {
            return Message::factory()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $randomUser->id,
                'content' => $markdownContent,
                'created_at' => now()->subHours(3),
            ]);
        });

        // Test account reads the message
        MessageRead::factory()->create([
            'message_id' => $markdownMessage->id,
            'user_id' => $testAccount->id,
            'read_at' => now()->subHours(2),
        ]);

        // Test account responds
        $response = Message::withoutEvents(function () use ($conversation, $testAccount) {
            return Message::factory()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $testAccount->id,
                'content' => "Thanks for sharing this! The **markdown formatting** looks great.\n\nI especially like:\n- The code examples\n- The organized structure\n- The helpful links\n\nI'll give it a try and let you know how it goes!",
                'created_at' => now()->subHours(2),
            ]);
        });

        // Random user reads the response
        MessageRead::factory()->create([
            'message_id' => $response->id,
            'user_id' => $randomUser->id,
            'read_at' => now()->subHour(),
        ]);

        // Random user sends another message
        $finalMessage = Message::withoutEvents(function () use ($conversation, $randomUser) {
            return Message::factory()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $randomUser->id,
                'content' => "You're welcome! Let me know if you need any help with the `configuration settings` or if you run into any ~~problems~~ issues.",
                'created_at' => now()->subMinutes(30),
            ]);
        });

        // Update conversation's last message fields
        $conversation->update([
            'last_message_id' => $finalMessage->id,
            'last_message_at' => $finalMessage->created_at,
        ]);

        $this->command->outputComponents()->info("Created markdown test conversation between {$randomUser->email} and {$testAccount->email}");
    }
}
