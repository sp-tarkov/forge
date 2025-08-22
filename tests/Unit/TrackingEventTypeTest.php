<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;

describe('TrackingEventType', function (): void {
    describe('isPrivate method', function (): void {
        it('returns true for authentication, account management, and reporting events', function (): void {
            expect(TrackingEventType::LOGIN->isPrivate())->toBeTrue();
            expect(TrackingEventType::LOGOUT->isPrivate())->toBeTrue();
            expect(TrackingEventType::REGISTER->isPrivate())->toBeTrue();
            expect(TrackingEventType::PASSWORD_CHANGE->isPrivate())->toBeTrue();
            expect(TrackingEventType::ACCOUNT_DELETE->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_REPORT->isPrivate())->toBeTrue();
            expect(TrackingEventType::COMMENT_REPORT->isPrivate())->toBeTrue();
        });

        it('returns false for public activity events', function (): void {
            expect(TrackingEventType::MOD_DOWNLOAD->isPrivate())->toBeFalse();
            expect(TrackingEventType::MOD_CREATE->isPrivate())->toBeFalse();
            expect(TrackingEventType::MOD_EDIT->isPrivate())->toBeFalse();
            expect(TrackingEventType::MOD_DELETE->isPrivate())->toBeFalse();
            expect(TrackingEventType::VERSION_CREATE->isPrivate())->toBeFalse();
            expect(TrackingEventType::VERSION_EDIT->isPrivate())->toBeFalse();
            expect(TrackingEventType::VERSION_DELETE->isPrivate())->toBeFalse();
            expect(TrackingEventType::COMMENT_CREATE->isPrivate())->toBeFalse();
            expect(TrackingEventType::COMMENT_EDIT->isPrivate())->toBeFalse();
            expect(TrackingEventType::COMMENT_DELETE->isPrivate())->toBeFalse();
            expect(TrackingEventType::COMMENT_LIKE->isPrivate())->toBeFalse();
            expect(TrackingEventType::COMMENT_UNLIKE->isPrivate())->toBeFalse();
        });
    });
});
