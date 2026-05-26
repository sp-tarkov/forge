<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\ContentGuidelinesUpdatedNotification;
use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\ResetPassword;
use App\Notifications\UserBannedNotification;
use App\Notifications\VerifyEmail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Mchev\Banhammer\Models\Ban;
use Throwable;

#[Description('Send every notification email to the given user via the mail channel only. Local/development use only.')]
#[Signature('notifications:test-email {email : Recipient user email address}')]
final class SendTestEmails extends Command
{
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production. Use this command only in local/development environments.');

            return Command::FAILURE;
        }

        /** @var string $email */
        $email = $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->error(sprintf('No user found with email [%s].', $email));

            return Command::FAILURE;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($this->buildNotifications($user) as $label => $notification) {
            try {
                NotificationFacade::sendNow($user, $notification, ['mail']);
                $this->info(sprintf('Sent %s to %s', $label, $user->email));
                $sent++;
            } catch (Throwable $e) {
                $this->warn(sprintf('Skipped %s: %s', $label, $e->getMessage()));
                $skipped++;
            }
        }

        $this->newLine();
        $this->line(sprintf('Done. %d sent, %d skipped.', $sent, $skipped));

        return Command::SUCCESS;
    }

    /**
     * Build one of every notification, keyed by a friendly label. Each entry is dispatched in handle(); failures are
     * caught and logged there.
     *
     * @return array<string, Notification>
     */
    private function buildNotifications(User $user): array
    {
        return [
            'ContentGuidelinesUpdated' => new ContentGuidelinesUpdatedNotification,
            'NewChatMessage' => $this->buildChatNotification($user),
            'NewComment' => new NewCommentNotification($this->latestOrFakeComment()),
            'CommentReply' => new CommentReplyNotification($this->latestOrFakeComment()),
            'ReportSubmitted' => new ReportSubmittedNotification($this->latestOrFakeReport()),
            'UserBanned' => $this->buildBanNotification($user),
            'ResetPassword' => new ResetPassword('test-reset-token-'.bin2hex(random_bytes(16))),
            'VerifyEmail' => new VerifyEmail,
        ];
    }

    private function buildChatNotification(User $user): NewChatMessageNotification
    {
        $conversation = Conversation::query()
            ->where('user1_id', $user->id)
            ->orWhere('user2_id', $user->id)
            ->latest()
            ->first();

        if (! $conversation instanceof Conversation) {
            $other = User::query()->where('id', '!=', $user->id)->inRandomOrder()->first();
            $other ??= User::factory()->create();

            /** @var Conversation $conversation */
            $conversation = Conversation::factory()->withUsers($user, $other)->create();
        }

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->latest()
            ->limit(3)
            ->get();

        if ($messages->isEmpty()) {
            $sender = $conversation->getOtherUser($user) ?? User::factory()->create();
            $messages = collect([
                Message::factory()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $sender->id,
                    'content' => 'This is a test message used to render the new-chat-message email template.',
                ]),
            ]);
        }

        return new NewChatMessageNotification($conversation, $messages);
    }

    private function latestOrFakeComment(): Comment
    {
        $comment = Comment::query()
            ->whereHasMorph('commentable', [Mod::class])
            ->latest()
            ->first();

        return $comment ?? Comment::factory()->create();
    }

    private function latestOrFakeReport(): Report
    {
        $report = Report::query()
            ->whereHasMorph('reportable', [User::class, Mod::class, Comment::class])
            ->latest()
            ->first();

        return $report ?? Report::factory()->create();
    }

    private function buildBanNotification(User $user): UserBannedNotification
    {
        $ban = new Ban([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => 'Test ban for email rendering only; no actual suspension is being applied.',
            'expired_at' => now()->addDays(7),
        ]);
        $ban->setAttribute('id', 0);
        $ban->setAttribute('created_at', now());

        return new UserBannedNotification($ban);
    }
}
