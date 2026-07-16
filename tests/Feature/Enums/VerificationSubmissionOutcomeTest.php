<?php

declare(strict_types=1);

use App\Enums\VerificationSubmissionOutcome;

describe('toastHeading', function (): void {
    it('returns a heading for every outcome', function (VerificationSubmissionOutcome $outcome, string $expected): void {
        expect($outcome->toastHeading())->toBe($expected);
    })->with([
        'queued' => [VerificationSubmissionOutcome::Queued, 'Verification Queued'],
        'already queued' => [VerificationSubmissionOutcome::AlreadyQueued, 'Already Pending'],
        'rate limited' => [VerificationSubmissionOutcome::RateLimited, 'Too Many Submissions'],
        'ineligible' => [VerificationSubmissionOutcome::Ineligible, 'Not Eligible'],
        'missing link' => [VerificationSubmissionOutcome::MissingLink, 'Error'],
    ]);
});

describe('toastText', function (): void {
    it('offers a non-empty message for every outcome', function (VerificationSubmissionOutcome $outcome): void {
        expect($outcome->toastText())->toBeString()->not->toBeEmpty();
    })->with(VerificationSubmissionOutcome::cases());

    it('rounds the rate limit retry delay up to whole minutes', function (?int $seconds, string $expected): void {
        expect(VerificationSubmissionOutcome::RateLimited->toastText($seconds))->toContain($expected);
    })->with([
        'null delay' => [null, 'in 1 minute.'],
        'under a minute' => [30, 'in 1 minute.'],
        'over a minute' => [90, 'in 2 minutes.'],
        'exact hour' => [3600, 'in 60 minutes.'],
    ]);

    it('includes the minimum SPT version in the ineligible message', function (): void {
        config()->set('verification.min_spt_version', '4.1.0');

        expect(VerificationSubmissionOutcome::Ineligible->toastText())->toContain('4.1.0');
    });
});

describe('toastVariant', function (): void {
    it('returns a variant for every outcome', function (VerificationSubmissionOutcome $outcome, string $expected): void {
        expect($outcome->toastVariant())->toBe($expected);
    })->with([
        'queued' => [VerificationSubmissionOutcome::Queued, 'success'],
        'already queued' => [VerificationSubmissionOutcome::AlreadyQueued, 'warning'],
        'rate limited' => [VerificationSubmissionOutcome::RateLimited, 'warning'],
        'ineligible' => [VerificationSubmissionOutcome::Ineligible, 'warning'],
        'missing link' => [VerificationSubmissionOutcome::MissingLink, 'danger'],
    ]);
});
