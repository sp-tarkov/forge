<?php

declare(strict_types=1);

use App\Enums\HeadlineEmphasis;
use App\Enums\NotificationColorRole;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Notifications\ContentGuidelinesUpdatedNotification;
use App\Notifications\NewChatMessageNotification;
use App\Notifications\NewCommentNotification;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\UserBannedNotification;
use Illuminate\Notifications\DatabaseNotification;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

/**
 * @param  array<string, mixed>  $data
 */
function makeNotification(User $user, string $type, array $data): DatabaseNotification
{
    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => $type,
        'data' => $data,
        'read_at' => null,
    ]);

    return $notification;
}

describe('ReportSubmittedNotification', function (): void {
    it('builds a red exclamation-triangle presentation with reporter, reason, and url', function (): void {
        $record = makeNotification($this->user, ReportSubmittedNotification::class, [
            'reporter_name' => 'Reporter User',
            'reportable_title' => 'Bad Mod',
            'reportable_url' => '/mod/1/bad-mod',
            'reason_label' => 'Spam',
            'context' => 'Looks like spam.',
        ]);

        $presentation = ReportSubmittedNotification::presentDatabaseNotification($record);

        expect($presentation->iconName)->toBe('exclamation-triangle');
        expect($presentation->iconColorRole)->toBe(NotificationColorRole::Red);
        expect($presentation->headline)->toHaveCount(3);
        expect($presentation->headline[0]->text)->toBe('Reporter User');
        expect($presentation->headline[0]->emphasis)->toBe(HeadlineEmphasis::Strong);
        expect($presentation->headline[2]->text)->toBe('spam');
        expect($presentation->headline[2]->emphasis)->toBe(HeadlineEmphasis::Accent);
        expect($presentation->summary)->toContain('reported');
        expect($presentation->summary)->toContain('Bad Mod');
        expect($presentation->preview)->toBe('Looks like spam.');
        expect($presentation->previewQuoted)->toBeTrue();
        expect($presentation->url)->toBe('/mod/1/bad-mod');
    });

    it('falls back to defaults when data keys are missing', function (): void {
        $record = makeNotification($this->user, ReportSubmittedNotification::class, []);

        $presentation = ReportSubmittedNotification::presentDatabaseNotification($record);

        expect($presentation->headline[0]->text)->toBe(__('Someone'));
        expect($presentation->preview)->toBeNull();
        expect($presentation->url)->toBeNull();
    });
});

describe('NewChatMessageNotification', function (): void {
    it('builds a purple chat-bubble presentation with sender and singular message wording', function (): void {
        $record = makeNotification($this->user, NewChatMessageNotification::class, [
            'sender_name' => 'Sender',
            'message_count' => 1,
            'conversation_url' => '/chat/abc',
            'latest_message_preview' => 'Hi there.',
        ]);

        $presentation = NewChatMessageNotification::presentDatabaseNotification($record);

        expect($presentation->iconName)->toBe('chat-bubble-left-right');
        expect($presentation->iconColorRole)->toBe(NotificationColorRole::Purple);
        expect($presentation->headline[0]->text)->toBe('Sender');
        expect($presentation->headline[2]->text)->toBe(__('new message'));
        expect($presentation->summary)->toBe(__('sent you a message'));
        expect($presentation->preview)->toBe('Hi there.');
        expect($presentation->url)->toBe('/chat/abc');
    });

    it('uses plural wording when message_count is greater than one', function (): void {
        $record = makeNotification($this->user, NewChatMessageNotification::class, [
            'sender_name' => 'Sender',
            'message_count' => 3,
            'conversation_url' => '/chat/abc',
            'latest_message_preview' => 'Hi there.',
        ]);

        $presentation = NewChatMessageNotification::presentDatabaseNotification($record);

        expect($presentation->headline[2]->text)->toContain('3');
        expect($presentation->summary)->toContain('3');
    });
});

describe('NewCommentNotification', function (): void {
    it('builds a blue comment-bubble presentation with commenter and target title', function (): void {
        $record = makeNotification($this->user, NewCommentNotification::class, [
            'commenter_name' => 'Commenter',
            'commentable_title' => 'Some Mod',
            'comment_url' => '/mod/1/slug#comments-comment-1',
            'comment_body' => 'Nice mod.',
        ]);

        $presentation = NewCommentNotification::presentDatabaseNotification($record);

        expect($presentation->iconName)->toBe('chat-bubble-left-ellipsis');
        expect($presentation->iconColorRole)->toBe(NotificationColorRole::Blue);
        expect($presentation->headline[0]->text)->toBe('Commenter');
        expect($presentation->headline[2]->text)->toBe('Some Mod');
        expect($presentation->preview)->toBe('Nice mod.');
        expect($presentation->url)->toBe('/mod/1/slug#comments-comment-1');
    });
});

describe('CommentReplyNotification', function (): void {
    it('renders identically to NewCommentNotification by default', function (): void {
        $payload = [
            'commenter_name' => 'Replier',
            'commentable_title' => 'Some Mod',
            'comment_url' => '/mod/1/slug#comments-comment-2',
            'comment_body' => 'A reply.',
            'is_reply' => true,
        ];

        $reply = CommentReplyNotification::presentDatabaseNotification(makeNotification($this->user, CommentReplyNotification::class, $payload));
        $comment = NewCommentNotification::presentDatabaseNotification(makeNotification($this->user, NewCommentNotification::class, $payload));

        expect($reply->iconName)->toBe($comment->iconName);
        expect($reply->iconColorRole)->toBe($comment->iconColorRole);
        expect($reply->summary)->toBe($comment->summary);
        expect($reply->url)->toBe($comment->url);
    });
});

describe('ContentGuidelinesUpdatedNotification', function (): void {
    it('builds an amber megaphone presentation with title, body, and url', function (): void {
        $record = makeNotification($this->user, ContentGuidelinesUpdatedNotification::class, [
            'title' => 'Content Guidelines Updated',
            'body' => 'Body text describing the change.',
            'url' => '/content-guidelines',
        ]);

        $presentation = ContentGuidelinesUpdatedNotification::presentDatabaseNotification($record);

        expect($presentation->iconName)->toBe('megaphone');
        expect($presentation->iconColorRole)->toBe(NotificationColorRole::Amber);
        expect($presentation->headline)->toHaveCount(1);
        expect($presentation->headline[0]->text)->toBe('Content Guidelines Updated');
        expect($presentation->headline[0]->emphasis)->toBe(HeadlineEmphasis::Accent);
        expect($presentation->preview)->toBe('Body text describing the change.');
        expect($presentation->previewQuoted)->toBeFalse();
        expect($presentation->url)->toBe('/content-guidelines');
    });

    it('returns null preview and null url when data is empty', function (): void {
        $record = makeNotification($this->user, ContentGuidelinesUpdatedNotification::class, []);

        $presentation = ContentGuidelinesUpdatedNotification::presentDatabaseNotification($record);

        expect($presentation->preview)->toBeNull();
        expect($presentation->url)->toBeNull();
    });
});

describe('UserBannedNotification', function (): void {
    it('builds a red no-symbol presentation with permanent duration', function (): void {
        $record = makeNotification($this->user, UserBannedNotification::class, [
            'ban_id' => 1,
            'reason' => 'Repeated abuse.',
            'is_permanent' => true,
            'expired_at' => null,
        ]);

        $presentation = UserBannedNotification::presentDatabaseNotification($record);

        expect($presentation->iconName)->toBe('no-symbol');
        expect($presentation->iconColorRole)->toBe(NotificationColorRole::Red);
        expect($presentation->headline[2]->text)->toBe(__('permanent'));
        expect($presentation->preview)->toBe('Repeated abuse.');
        expect($presentation->url)->toBeNull();
    });

    it('reports a temporary suspension when is_permanent is false', function (): void {
        $record = makeNotification($this->user, UserBannedNotification::class, [
            'is_permanent' => false,
        ]);

        $presentation = UserBannedNotification::presentDatabaseNotification($record);

        expect($presentation->headline[2]->text)->toBe(__('temporary'));
    });
});
