<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Notification Delivery', function (): void {
    it('sends notification via database channel', function (): void {
        $reporter = User::factory()->create();
        $mod = Mod::factory()->create();
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
            'reason' => ReportReason::SPAM,
            'context' => 'This is spam content',
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);

        $channels = $notification->via($moderator);

        expect($channels)->toContain('database');
    });

    it('sends notification via mail channel when user has email notifications enabled', function (): void {
        $reporter = User::factory()->create();
        $mod = Mod::factory()->create();
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
            'reason' => ReportReason::SPAM,
        ]);

        $moderator = User::factory()->create(['email_notifications_enabled' => true]);
        $notification = new ReportSubmittedNotification($report);

        $channels = $notification->via($moderator);

        expect($channels)->toContain('database')
            ->and($channels)->toContain('mail');
    });

    it('does not send mail when user has email notifications disabled', function (): void {
        $reporter = User::factory()->create();
        $mod = Mod::factory()->create();
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
            'reason' => ReportReason::SPAM,
        ]);

        $moderator = User::factory()->create(['email_notifications_enabled' => false]);
        $notification = new ReportSubmittedNotification($report);

        $channels = $notification->via($moderator);

        expect($channels)->toContain('database')
            ->and($channels)->not->toContain('mail');
    });
});

describe('Mail Message Content', function (): void {
    it('creates correct mail message for mod report', function (): void {
        $reporter = User::factory()->create(['name' => 'John Reporter']);
        $mod = Mod::factory()->create([
            'name' => 'Test Mod',
            'slug' => 'test-mod',
            'description' => 'This is a test mod with some description that should be truncated if too long for the notification system',
        ]);
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
            'reason' => ReportReason::INAPPROPRIATE_CONTENT,
            'context' => 'This content is inappropriate',
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);
        $mailMessage = $notification->toMail($moderator);

        expect($mailMessage->subject)->toBe('New Content Report: Inappropriate Content')
            ->and($mailMessage->introLines[0])->toContain('John Reporter has reported mod "Test Mod" for: Inappropriate Content')
            ->and($mailMessage->introLines[1])->toBe('Additional context: This content is inappropriate')
            ->and($mailMessage->introLines[2])->toContain('Content preview: This is a test mod with some description that should be');
    });

    it('creates correct mail message for comment report', function (): void {
        $reporter = User::factory()->create(['name' => 'Jane Reporter']);
        $comment = Comment::factory()->create([
            'body' => 'This is a comment that contains some inappropriate content that might be offensive to some users',
        ]);
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $comment::class,
            'reportable_id' => $comment->id,
            'reason' => ReportReason::HARASSMENT,
            'context' => null,
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);
        $mailMessage = $notification->toMail($moderator);

        expect($mailMessage->subject)->toBe('New Content Report: Harassment')
            ->and($mailMessage->introLines[0])->toContain('Jane Reporter has reported comment "comment #'.$comment->id.'" for: Harassment')
            ->and($mailMessage->introLines[1])->toContain('Content preview: This is a comment that contains some inappropriate content');
    });

    it('creates correct mail message for user report', function (): void {
        $reporter = User::factory()->create(['name' => 'Admin User']);
        $reportedUser = User::factory()->create([
            'name' => 'Bad User',
            'about' => 'This user has some concerning information in their about section',
        ]);
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $reportedUser::class,
            'reportable_id' => $reportedUser->id,
            'reason' => ReportReason::OTHER,
            'context' => 'Suspicious activity detected',
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);
        $mailMessage = $notification->toMail($moderator);

        expect($mailMessage->subject)->toBe('New Content Report: Other')
            ->and($mailMessage->introLines[0])->toContain('Admin User has reported user profile "Bad User" for: Other')
            ->and($mailMessage->introLines[1])->toBe('Additional context: Suspicious activity detected')
            ->and($mailMessage->introLines[2])->toContain('Content preview: This user has some concerning information');
    });

    it('handles reports without context', function (): void {
        $reporter = User::factory()->create(['name' => 'Test Reporter']);
        $mod = Mod::factory()->create(['name' => 'Test Mod', 'slug' => 'test-mod']);
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
            'reason' => ReportReason::SPAM,
            'context' => null,
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);
        $mailMessage = $notification->toMail($moderator);

        expect($mailMessage->introLines)->toHaveCount(2)
            ->and($mailMessage->introLines[0])->toContain('Test Reporter has reported mod "Test Mod" for: Spam'); // No context line
    });
});

describe('Database Notification Data', function (): void {
    it('creates correct database notification data for mod report', function (): void {
        $reporter = User::factory()->create(['name' => 'John Doe']);
        $mod = Mod::factory()->create([
            'name' => 'Cool Mod',
            'slug' => 'cool-mod',
            'description' => 'A really cool mod that does amazing things',
        ]);
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
            'reason' => ReportReason::COPYRIGHT_VIOLATION,
            'context' => 'This violates my copyright',
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);
        $data = $notification->toArray($moderator);

        expect($data['report_id'])->toBe($report->id)
            ->and($data['reporter_name'])->toBe('John Doe')
            ->and($data['reporter_id'])->toBe($reporter->id)
            ->and($data['reason'])->toBe('copyright_violation')
            ->and($data['reason_label'])->toBe('Copyright Violation')
            ->and($data['context'])->toBe('This violates my copyright')
            ->and($data['reportable_type'])->toBe($mod::class)
            ->and($data['reportable_id'])->toBe($mod->id)
            ->and($data['reportable_title'])->toBe('Cool Mod')
            ->and($data['reportable_excerpt'])->toBe('A really cool mod that does amazing things')
            ->and($data['reportable_url'])->toBe(route('mod.show', [$mod->id, $mod->slug]));
    });

    it('creates correct database notification data for comment report', function (): void {
        $reporter = User::factory()->create(['name' => 'Reporter']);
        $comment = Comment::factory()->create([
            'body' => 'This is a very long comment that should be truncated when displayed in the notification to prevent the UI from becoming cluttered with too much text',
        ]);
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $comment::class,
            'reportable_id' => $comment->id,
            'reason' => ReportReason::MISINFORMATION,
            'context' => '',
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);
        $data = $notification->toArray($moderator);

        expect($data['reportable_title'])->toBe('comment #'.$comment->id)
            ->and($data['reportable_excerpt'])->toBe('This is a very long comment that should be truncated when displayed in the notification...')
            ->and($data['reportable_url'])->toBe($comment->getUrl())
            ->and($data['context'])->toBe('');
    });

    it('handles models without excerpts', function (): void {
        $reporter = User::factory()->create();
        $mod = Mod::factory()->create(['slug' => 'test-mod', 'description' => 'Required description']);
        $report = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
            'reason' => ReportReason::OTHER,
        ]);

        $moderator = User::factory()->create();
        $notification = new ReportSubmittedNotification($report);
        $data = $notification->toArray($moderator);

        expect($data['reportable_excerpt'])->toBe('Required description');
    });
});

describe('URL Generation', function (): void {
    it('generates correct URLs for different reportable types', function (): void {
        $reporter = User::factory()->create();
        $mod = Mod::factory()->create(['slug' => 'test-mod']);
        $comment = Comment::factory()->create();
        $user = User::factory()->create();

        $modReport = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $mod::class,
            'reportable_id' => $mod->id,
        ]);

        $commentReport = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $comment::class,
            'reportable_id' => $comment->id,
        ]);

        $userReport = Report::factory()->create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $user::class,
            'reportable_id' => $user->id,
        ]);

        $moderator = User::factory()->create();

        $modNotification = new ReportSubmittedNotification($modReport);
        $commentNotification = new ReportSubmittedNotification($commentReport);
        $userNotification = new ReportSubmittedNotification($userReport);

        expect($modNotification->toArray($moderator)['reportable_url'])->toBe(route('mod.show', [$mod->id, $mod->slug]))
            ->and($commentNotification->toArray($moderator)['reportable_url'])->toBe($comment->getUrl())
            ->and($userNotification->toArray($moderator)['reportable_url'])->toBe(route('user.show',
                [$user->id, $user->slug ?? 'user']));
    });
});
