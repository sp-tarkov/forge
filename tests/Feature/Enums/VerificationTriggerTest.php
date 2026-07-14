<?php

declare(strict_types=1);

use App\Enums\VerificationTrigger;

describe('label', function (): void {
    it('returns a human-readable label for every trigger', function (VerificationTrigger $trigger, string $expected): void {
        expect($trigger->label())->toBe($expected);
    })->with([
        'change detected' => [VerificationTrigger::ChangeDetected, 'Change Detected'],
        'manual' => [VerificationTrigger::Manual, 'Manual'],
        'upload' => [VerificationTrigger::Upload, 'Upload'],
    ]);

    it('offers a non-empty label for every case', function (VerificationTrigger $trigger): void {
        expect($trigger->label())->toBeString()->not->toBeEmpty();
    })->with(VerificationTrigger::cases());
});
