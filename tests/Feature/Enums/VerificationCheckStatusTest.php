<?php

declare(strict_types=1);

use App\Enums\VerificationCheckStatus;

describe('fromContainer', function (): void {
    it('resolves a known status value', function (string $value, VerificationCheckStatus $expected): void {
        expect(VerificationCheckStatus::fromContainer($value))->toBe($expected);
    })->with([
        'passed' => ['passed', VerificationCheckStatus::Passed],
        'failed' => ['failed', VerificationCheckStatus::Failed],
        'skipped' => ['skipped', VerificationCheckStatus::Skipped],
    ]);

    it('falls back to a failure for an unknown or missing value', function (?string $value): void {
        expect(VerificationCheckStatus::fromContainer($value))->toBe(VerificationCheckStatus::Failed);
    })->with([
        'unknown' => 'weird',
        'empty' => '',
        'null' => null,
    ]);
});

describe('presentation', function (): void {
    it('offers a non-empty label and color for every case', function (VerificationCheckStatus $status): void {
        expect($status->label())->toBeString()->not->toBeEmpty();
        expect($status->color())->toBeString()->not->toBeEmpty();
    })->with(VerificationCheckStatus::cases());
});
