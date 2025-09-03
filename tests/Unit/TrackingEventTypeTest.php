<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\User;

describe('TrackingEventType', function (): void {
    describe('isPrivate method', function (): void {
        it('returns true for authentication, account management, reporting, and moderator action events', function (): void {
            expect(TrackingEventType::LOGIN->isPrivate())->toBeTrue();
            expect(TrackingEventType::LOGOUT->isPrivate())->toBeTrue();
            expect(TrackingEventType::REGISTER->isPrivate())->toBeTrue();
            expect(TrackingEventType::PASSWORD_CHANGE->isPrivate())->toBeTrue();
            expect(TrackingEventType::ACCOUNT_DELETE->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_REPORT->isPrivate())->toBeTrue();
            expect(TrackingEventType::COMMENT_REPORT->isPrivate())->toBeTrue();
            expect(TrackingEventType::USER_BAN->isPrivate())->toBeTrue();
            expect(TrackingEventType::USER_UNBAN->isPrivate())->toBeTrue();
            expect(TrackingEventType::IP_BAN->isPrivate())->toBeTrue();
            expect(TrackingEventType::IP_UNBAN->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_FEATURE->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_UNFEATURE->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_DISABLE->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_ENABLE->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_PUBLISH->isPrivate())->toBeTrue();
            expect(TrackingEventType::MOD_UNPUBLISH->isPrivate())->toBeTrue();
            expect(TrackingEventType::COMMENT_PIN->isPrivate())->toBeTrue();
            expect(TrackingEventType::COMMENT_UNPIN->isPrivate())->toBeTrue();
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

    describe('getName method', function (): void {
        it('returns correct display names for moderator action events', function (): void {
            expect(TrackingEventType::USER_BAN->getName())->toBe('Banned user');
            expect(TrackingEventType::USER_UNBAN->getName())->toBe('Unbanned user');
            expect(TrackingEventType::IP_BAN->getName())->toBe('Banned IP address');
            expect(TrackingEventType::IP_UNBAN->getName())->toBe('Unbanned IP address');
            expect(TrackingEventType::MOD_FEATURE->getName())->toBe('Featured mod');
            expect(TrackingEventType::MOD_UNFEATURE->getName())->toBe('Unfeatured mod');
            expect(TrackingEventType::MOD_DISABLE->getName())->toBe('Disabled mod');
            expect(TrackingEventType::MOD_ENABLE->getName())->toBe('Enabled mod');
            expect(TrackingEventType::MOD_PUBLISH->getName())->toBe('Published mod');
            expect(TrackingEventType::MOD_UNPUBLISH->getName())->toBe('Unpublished mod');
            expect(TrackingEventType::COMMENT_PIN->getName())->toBe('Pinned comment');
            expect(TrackingEventType::COMMENT_UNPIN->getName())->toBe('Unpinned comment');
        });
    });

    describe('getDescription method', function (): void {
        it('returns correct descriptions for moderator action events', function (): void {
            expect(TrackingEventType::USER_BAN->getDescription())->toBe('Moderator banned a user');
            expect(TrackingEventType::USER_UNBAN->getDescription())->toBe('Moderator unbanned a user');
            expect(TrackingEventType::IP_BAN->getDescription())->toBe('Moderator banned an IP address');
            expect(TrackingEventType::IP_UNBAN->getDescription())->toBe('Moderator unbanned an IP address');
            expect(TrackingEventType::MOD_FEATURE->getDescription())->toBe('Moderator featured a mod');
            expect(TrackingEventType::MOD_UNFEATURE->getDescription())->toBe('Moderator unfeatured a mod');
            expect(TrackingEventType::MOD_DISABLE->getDescription())->toBe('Moderator disabled a mod');
            expect(TrackingEventType::MOD_ENABLE->getDescription())->toBe('Moderator enabled a mod');
            expect(TrackingEventType::MOD_PUBLISH->getDescription())->toBe('Moderator published a mod');
            expect(TrackingEventType::MOD_UNPUBLISH->getDescription())->toBe('Moderator unpublished a mod');
            expect(TrackingEventType::COMMENT_PIN->getDescription())->toBe('Moderator pinned a comment');
            expect(TrackingEventType::COMMENT_UNPIN->getDescription())->toBe('Moderator unpinned a comment');
        });
    });

    describe('getTrackableModel method', function (): void {
        it('returns correct trackable models for moderator action events', function (): void {
            expect(TrackingEventType::USER_BAN->getTrackableModel())->toBe(User::class);
            expect(TrackingEventType::USER_UNBAN->getTrackableModel())->toBe(User::class);
            expect(TrackingEventType::IP_BAN->getTrackableModel())->toBeNull();
            expect(TrackingEventType::IP_UNBAN->getTrackableModel())->toBeNull();
            expect(TrackingEventType::MOD_FEATURE->getTrackableModel())->toBe(Mod::class);
            expect(TrackingEventType::MOD_UNFEATURE->getTrackableModel())->toBe(Mod::class);
            expect(TrackingEventType::MOD_DISABLE->getTrackableModel())->toBe(Mod::class);
            expect(TrackingEventType::MOD_ENABLE->getTrackableModel())->toBe(Mod::class);
            expect(TrackingEventType::MOD_PUBLISH->getTrackableModel())->toBe(Mod::class);
            expect(TrackingEventType::MOD_UNPUBLISH->getTrackableModel())->toBe(Mod::class);
            expect(TrackingEventType::COMMENT_PIN->getTrackableModel())->toBe(Comment::class);
            expect(TrackingEventType::COMMENT_UNPIN->getTrackableModel())->toBe(Comment::class);
        });
    });

    describe('requiresTrackable method', function (): void {
        it('correctly identifies which moderator events require trackable models', function (): void {
            expect(TrackingEventType::USER_BAN->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::USER_UNBAN->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::IP_BAN->requiresTrackable())->toBeFalse();
            expect(TrackingEventType::IP_UNBAN->requiresTrackable())->toBeFalse();
            expect(TrackingEventType::MOD_FEATURE->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::MOD_UNFEATURE->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::MOD_DISABLE->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::MOD_ENABLE->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::MOD_PUBLISH->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::MOD_UNPUBLISH->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::COMMENT_PIN->requiresTrackable())->toBeTrue();
            expect(TrackingEventType::COMMENT_UNPIN->requiresTrackable())->toBeTrue();
        });
    });
});
