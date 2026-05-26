<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ContentGuidelinesUpdatedNotification;
use Illuminate\Support\Facades\URL;

describe('Channel Selection', function (): void {
    it('always sends via the database channel', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => false,
        ]);

        $channels = (new ContentGuidelinesUpdatedNotification)->via($user);

        expect($channels)->toContain('database');
    });

    it('sends via mail when announcement preferences are enabled', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => true,
        ]);

        $channels = (new ContentGuidelinesUpdatedNotification)->via($user);

        expect($channels)->toContain('mail');
    });

    it('omits the mail channel when announcement preferences are disabled', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => false,
        ]);

        $channels = (new ContentGuidelinesUpdatedNotification)->via($user);

        expect($channels)->not->toContain('mail');
    });

    it('omits the mail channel when the email is not verified', function (): void {
        $user = User::factory()->unverified()->create([
            'email_announcement_notifications_enabled' => true,
        ]);

        $channels = (new ContentGuidelinesUpdatedNotification)->via($user);

        expect($channels)->toContain('database')->not->toContain('mail');
    });
});

describe('Mail Message Content', function (): void {
    it('includes the AI flag requirement in the body', function (): void {
        $user = User::factory()->create();

        $message = (new ContentGuidelinesUpdatedNotification)->toMail($user);

        expect($message->subject)->toBe('Content Guidelines Updated');

        $combined = implode(' ', $message->introLines).' '.implode(' ', $message->outroLines);
        expect($combined)->toContain('Contains AI Content');
    });

    it('includes a signed announcement unsubscribe url and a preferences link', function (): void {
        $user = User::factory()->create();

        $message = (new ContentGuidelinesUpdatedNotification)->toMail($user);

        $footer = implode(' ', $message->footerLines);

        expect($footer)
            ->toContain(route('announcement.unsubscribe', ['user' => $user->id], absolute: false))
            ->toContain(route('profile.show'));
    });
});

describe('Unsubscribe Endpoint', function (): void {
    it('disables the announcement preference for the targeted user', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => true,
        ]);

        $signedUrl = URL::signedRoute('announcement.unsubscribe', ['user' => $user->id]);

        $this->get($signedUrl)->assertOk();

        $user->refresh();
        expect($user->email_announcement_notifications_enabled)->toBeFalse();
    });

    it('rejects requests without a valid signature', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => true,
        ]);

        $this->get(route('announcement.unsubscribe', ['user' => $user->id]))->assertStatus(403);

        $user->refresh();
        expect($user->email_announcement_notifications_enabled)->toBeTrue();
    });
});

describe('Database Notification Data', function (): void {
    it('serializes the announcement payload', function (): void {
        $user = User::factory()->create();

        $data = (new ContentGuidelinesUpdatedNotification)->toArray($user);

        expect($data['title'])->toBe('Content Guidelines Updated');
        expect($data['url'])->toBe(route('static.content-guidelines'));
    });
});
