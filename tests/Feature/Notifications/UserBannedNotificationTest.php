<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserRole;
use App\Notifications\UserBannedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Mchev\Banhammer\Models\Ban;

uses(RefreshDatabase::class);

describe('Notification Delivery', function (): void {
    it('always sends notification via database channel', function (): void {
        $user = User::factory()->create();
        $ban = Ban::query()->create([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => 'Test ban',
            'expired_at' => now()->addDay(),
        ]);

        $notification = new UserBannedNotification($ban);
        $channels = $notification->via($user);

        expect($channels)->toContain('database');
    });

    it('always sends notification via mail channel regardless of user preferences', function (): void {
        $user = User::factory()->create([
            'email_comment_notifications_enabled' => false,
            'email_chat_notifications_enabled' => false,
        ]);
        $ban = Ban::query()->create([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => 'Test ban',
            'expired_at' => now()->addDay(),
        ]);

        $notification = new UserBannedNotification($ban);
        $channels = $notification->via($user);

        expect($channels)->toContain('mail')
            ->and($channels)->toContain('database');
    });
});

describe('Mail Message Content', function (): void {
    it('creates correct mail message for temporary ban', function (): void {
        $user = User::factory()->create();
        $expiredAt = now()->addDays(7);
        $ban = Ban::query()->create([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => 'Violated community guidelines',
            'expired_at' => $expiredAt,
        ]);

        $notification = new UserBannedNotification($ban);
        $mailMessage = $notification->toMail($user);

        expect($mailMessage->subject)->toBe('Your account has been suspended')
            ->and($mailMessage->greeting)->toBe('Hello,')
            ->and($mailMessage->introLines[0])->toContain('has been suspended')
            ->and($mailMessage->introLines[1])->toContain('Until')
            ->and($mailMessage->introLines[2])->toContain('Violated community guidelines')
            ->and($mailMessage->introLines)->toContain('Your access will be automatically restored when the suspension period ends.');
    });

    it('creates correct mail message for permanent ban', function (): void {
        $user = User::factory()->create();
        $ban = Ban::query()->create([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => 'Severe policy violation',
            'expired_at' => null,
        ]);

        $notification = new UserBannedNotification($ban);
        $mailMessage = $notification->toMail($user);

        expect($mailMessage->subject)->toBe('Your account has been suspended')
            ->and($mailMessage->introLines[1])->toContain('Permanent')
            ->and($mailMessage->introLines[2])->toContain('Severe policy violation')
            ->and($mailMessage->introLines)->toContain('This suspension is permanent. If you believe this was done in error, please contact us for assistance.');
    });

    it('creates correct mail message for ban without reason', function (): void {
        $user = User::factory()->create();
        $ban = Ban::query()->create([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => null,
            'expired_at' => now()->addDay(),
        ]);

        $notification = new UserBannedNotification($ban);
        $mailMessage = $notification->toMail($user);

        $reasonLines = array_filter($mailMessage->introLines, fn ($line): bool => str_contains($line, '**Reason:**'));

        expect($reasonLines)->toBeEmpty();
    });
});

describe('Database Notification Data', function (): void {
    it('creates correct database notification data for temporary ban', function (): void {
        $user = User::factory()->create();
        $expiredAt = now()->addDays(7);
        $ban = Ban::query()->create([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => 'Test reason',
            'expired_at' => $expiredAt,
        ]);

        $notification = new UserBannedNotification($ban);
        $data = $notification->toArray($user);

        expect($data['ban_id'])->toBe($ban->id)
            ->and($data['reason'])->toBe('Test reason')
            ->and($data['expired_at'])->toBe($expiredAt->toIso8601String())
            ->and($data['is_permanent'])->toBeFalse()
            ->and($data['created_at'])->toBe($ban->created_at->toIso8601String());
    });

    it('creates correct database notification data for permanent ban', function (): void {
        $user = User::factory()->create();
        $ban = Ban::query()->create([
            'bannable_type' => User::class,
            'bannable_id' => $user->id,
            'comment' => null,
            'expired_at' => null,
        ]);

        $notification = new UserBannedNotification($ban);
        $data = $notification->toArray($user);

        expect($data['ban_id'])->toBe($ban->id)
            ->and($data['reason'])->toBeNull()
            ->and($data['expired_at'])->toBeNull()
            ->and($data['is_permanent'])->toBeTrue();
    });
});

describe('BanAction Integration', function (): void {
    it('sends notification when user is banned with duration', function (): void {
        Notification::fake();

        $adminRole = UserRole::factory()->create(['name' => 'Staff']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('user.ban-action', ['user' => $user])
            ->set('duration', '24_hours')
            ->set('reason', 'Testing ban notification')
            ->call('ban');

        Notification::assertSentTo(
            [$user],
            UserBannedNotification::class,
            fn ($notification): bool => $notification->ban->comment === 'Testing ban notification'
                && $notification->ban->expired_at !== null
        );
    });

    it('sends notification when user is banned permanently', function (): void {
        Notification::fake();

        $adminRole = UserRole::factory()->create(['name' => 'Staff']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('user.ban-action', ['user' => $user])
            ->set('duration', 'permanent')
            ->call('ban');

        Notification::assertSentTo(
            [$user],
            UserBannedNotification::class,
            fn ($notification): bool => $notification->ban->expired_at === null
        );
    });

    it('sends notification regardless of user email preferences', function (): void {
        Notification::fake();

        $adminRole = UserRole::factory()->create(['name' => 'Staff']);
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $user = User::factory()->create([
            'email_comment_notifications_enabled' => false,
            'email_chat_notifications_enabled' => false,
        ]);

        Livewire::actingAs($admin)
            ->test('user.ban-action', ['user' => $user])
            ->set('duration', '7_days')
            ->call('ban');

        Notification::assertSentTo([$user], UserBannedNotification::class);
    });
});
