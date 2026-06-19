<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ModVersionsDisabledNotification;

/**
 * @return array<int, array{mod_name: string, version: string, url: string, reason: string}>
 */
function sampleDisabledVersions(): array
{
    return [
        [
            'mod_name' => 'SAIN',
            'version' => '4.4.1-FikaEnhanced',
            'url' => 'https://forge.test/mod/791/version/13340/edit',
            'reason' => 'The "-FikaEnhanced" label is valid SemVer but cannot be used for dependency matching.',
        ],
    ];
}

describe('Channel Selection', function (): void {
    it('always sends via the database channel', function (): void {
        $user = User::factory()->create(['email_announcement_notifications_enabled' => false]);

        $channels = new ModVersionsDisabledNotification(sampleDisabledVersions())->via($user);

        expect($channels)->toContain('database');
    });

    it('sends via mail when the administrative email preference is enabled', function (): void {
        $user = User::factory()->create(['email_announcement_notifications_enabled' => true]);

        $channels = new ModVersionsDisabledNotification(sampleDisabledVersions())->via($user);

        expect($channels)->toContain('mail');
    });

    it('omits the mail channel when the administrative email preference is disabled', function (): void {
        $user = User::factory()->create(['email_announcement_notifications_enabled' => false]);

        $channels = new ModVersionsDisabledNotification(sampleDisabledVersions())->via($user);

        expect($channels)->not->toContain('mail');
    });

    it('omits the mail channel when the email is not verified', function (): void {
        $user = User::factory()->unverified()->create(['email_announcement_notifications_enabled' => true]);

        $channels = new ModVersionsDisabledNotification(sampleDisabledVersions())->via($user);

        expect($channels)->toContain('database')->not->toContain('mail');
    });
});

describe('Mail Message Content', function (): void {
    it('lists each disabled version with its link and reason', function (): void {
        $user = User::factory()->create();

        $message = new ModVersionsDisabledNotification(sampleDisabledVersions())->toMail($user);

        expect($message->subject)->toBe('Mod versions unpublished: invalid version numbers');

        $body = implode(' ', $message->introLines).' '.implode(' ', $message->outroLines);

        expect($body)
            ->toContain('SAIN')
            ->toContain('4.4.1-FikaEnhanced')
            ->toContain('https://forge.test/mod/791/version/13340/edit')
            ->toContain('cannot be used for dependency matching');
    });

    it('includes a signed announcement unsubscribe url and a preferences link', function (): void {
        $user = User::factory()->create();

        $message = new ModVersionsDisabledNotification(sampleDisabledVersions())->toMail($user);

        $footer = implode(' ', $message->footerLines);

        expect($footer)
            ->toContain(route('announcement.unsubscribe', ['user' => $user->id], absolute: false))
            ->toContain(route('profile.show'));
    });
});

describe('Database Notification Data', function (): void {
    it('serializes the disabled version payload', function (): void {
        $user = User::factory()->create();
        $versions = sampleDisabledVersions();

        $data = new ModVersionsDisabledNotification($versions)->toArray($user);

        expect($data['title'])->toBe('Versions unpublished')
            ->and($data['versions'])->toBe($versions)
            ->and($data['url'])->toBe($user->profile_url.'#mods');
    });
});

describe('Dashboard Presentation', function (): void {
    it('exposes each version as a linked, annotated detail line', function (): void {
        $user = User::factory()->create();
        $versions = sampleDisabledVersions();

        $record = $user->notifications()->create([
            'id' => fake()->uuid(),
            'type' => ModVersionsDisabledNotification::class,
            'data' => new ModVersionsDisabledNotification($versions)->toArray($user),
            'read_at' => null,
        ]);

        $presentation = ModVersionsDisabledNotification::presentDatabaseNotification($record);

        expect($presentation->details)->toHaveCount(1)
            ->and($presentation->details[0]->label)->toBe('SAIN 4.4.1-FikaEnhanced')
            ->and($presentation->details[0]->url)->toBe($versions[0]['url'])
            ->and($presentation->details[0]->note)->toBe($versions[0]['reason'])
            ->and($presentation->headline[0]->text)->toContain('unpublished')
            ->and($presentation->preview)->toContain('dependency matching')
            ->and($presentation->url)->toBe($user->profile_url.'#mods');
    });
});

describe('Mail Action Target', function (): void {
    it('points the mail action at the author mod listing', function (): void {
        $user = User::factory()->create();

        $message = new ModVersionsDisabledNotification(sampleDisabledVersions())->toMail($user);

        expect($message->actionUrl)->toBe($user->profile_url.'#mods');
    });
});
