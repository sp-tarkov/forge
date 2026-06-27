<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\TermsOfServiceUpdatedNotification;

describe('Channel Selection', function (): void {
    it('always sends via the database channel', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => false,
        ]);

        $channels = (new TermsOfServiceUpdatedNotification)->via($user);

        expect($channels)->toContain('database');
    });

    it('sends via mail when announcement preferences are enabled', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => true,
        ]);

        $channels = (new TermsOfServiceUpdatedNotification)->via($user);

        expect($channels)->toContain('mail');
    });

    it('omits the mail channel when announcement preferences are disabled', function (): void {
        $user = User::factory()->create([
            'email_announcement_notifications_enabled' => false,
        ]);

        $channels = (new TermsOfServiceUpdatedNotification)->via($user);

        expect($channels)->not->toContain('mail');
    });

    it('omits the mail channel when the email is not verified', function (): void {
        $user = User::factory()->unverified()->create([
            'email_announcement_notifications_enabled' => true,
        ]);

        $channels = (new TermsOfServiceUpdatedNotification)->via($user);

        expect($channels)->toContain('database')->not->toContain('mail');
    });
});

describe('Mail Message Content', function (): void {
    it('describes the automated-access clarification in the body', function (): void {
        $user = User::factory()->create();

        $message = (new TermsOfServiceUpdatedNotification)->toMail($user);

        expect($message->subject)->toBe('Terms of Service Updated');

        $combined = implode(' ', $message->introLines).' '.implode(' ', $message->outroLines);
        expect($combined)->toContain('public API');
    });

    it('includes a signed announcement unsubscribe url and a preferences link', function (): void {
        $user = User::factory()->create();

        $message = (new TermsOfServiceUpdatedNotification)->toMail($user);

        $footer = implode(' ', $message->footerLines);

        expect($footer)
            ->toContain(route('announcement.unsubscribe', ['user' => $user->id], absolute: false))
            ->toContain(route('profile.show'));
    });
});

describe('Database Notification Data', function (): void {
    it('serializes the announcement payload', function (): void {
        $user = User::factory()->create();

        $data = (new TermsOfServiceUpdatedNotification)->toArray($user);

        expect($data['title'])->toBe('Terms of Service Updated');
        expect($data['url'])->toBe(route('static.terms'));
    });
});
