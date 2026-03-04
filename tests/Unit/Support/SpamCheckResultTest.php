<?php

declare(strict_types=1);

use App\Enums\SpamStatus;
use App\Support\Akismet\SpamCheckResult;

it('returns approve action for clean content', function (): void {
    $result = new SpamCheckResult(isSpam: false, metadata: ['score' => 0.1]);

    expect($result->isSpam)->toBeFalse()
        ->and($result->shouldAutoDelete())->toBeFalse()
        ->and($result->getRecommendedAction())->toBe('approve')
        ->and($result->getSpamStatus())->toBe(SpamStatus::CLEAN);
});

it('returns review action for spam without discard', function (): void {
    $result = new SpamCheckResult(isSpam: true, metadata: ['score' => 0.9]);

    expect($result->isSpam)->toBeTrue()
        ->and($result->shouldAutoDelete())->toBeFalse()
        ->and($result->getRecommendedAction())->toBe('review')
        ->and($result->getSpamStatus())->toBe(SpamStatus::SPAM);
});

it('returns delete action for spam with discard', function (): void {
    $result = new SpamCheckResult(isSpam: true, metadata: ['score' => 1.0], discard: true);

    expect($result->isSpam)->toBeTrue()
        ->and($result->shouldAutoDelete())->toBeTrue()
        ->and($result->getRecommendedAction())->toBe('delete')
        ->and($result->getSpamStatus())->toBe(SpamStatus::SPAM);
});

it('does not auto-delete clean content even with discard flag', function (): void {
    $result = new SpamCheckResult(isSpam: false, metadata: [], discard: true);

    expect($result->shouldAutoDelete())->toBeFalse()
        ->and($result->getRecommendedAction())->toBe('approve')
        ->and($result->getSpamStatus())->toBe(SpamStatus::SPAM);
});

it('stores optional pro tip and recheck after values', function (): void {
    $result = new SpamCheckResult(
        isSpam: false,
        metadata: ['source' => 'akismet'],
        proTip: 'Consider upgrading',
        recheckAfter: '2026-04-01',
    );

    expect($result->proTip)->toBe('Consider upgrading')
        ->and($result->recheckAfter)->toBe('2026-04-01');
});

it('defaults optional parameters to null', function (): void {
    $result = new SpamCheckResult(isSpam: false, metadata: []);

    expect($result->discard)->toBeFalse()
        ->and($result->proTip)->toBeNull()
        ->and($result->recheckAfter)->toBeNull();
});
