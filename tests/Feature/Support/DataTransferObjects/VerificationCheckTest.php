<?php

declare(strict_types=1);

use App\Enums\VerificationCheckStatus;
use App\Support\DataTransferObjects\VerificationCheck;

describe('fromContainer', function (): void {
    it('builds a check from a well-formed entry', function (): void {
        $check = VerificationCheck::fromContainer([
            'name' => 'archive_extraction',
            'status' => 'passed',
            'report_only' => false,
            'message' => null,
            'data' => ['files' => 3],
        ]);

        expect($check->name)->toBe('archive_extraction');
        expect($check->status)->toBe(VerificationCheckStatus::Passed);
        expect($check->reportOnly)->toBeFalse();
        expect($check->message)->toBeNull();
        expect($check->data)->toBe(['files' => 3]);
    });

    it('resolves an unrecognized status to a failure', function (): void {
        expect(VerificationCheck::fromContainer(['name' => 'x', 'status' => 'bogus'])->status)
            ->toBe(VerificationCheckStatus::Failed);
    });

    it('falls back to a placeholder name when missing or blank', function (mixed $name): void {
        expect(VerificationCheck::fromContainer(['name' => $name, 'status' => 'passed'])->name)->toBe('unknown');
    })->with([
        'missing' => [null],
        'blank' => [''],
        'non-string' => [42],
    ]);

    it('coerces report_only to a boolean and defaults it to false', function (): void {
        expect(VerificationCheck::fromContainer(['name' => 'x', 'status' => 'passed'])->reportOnly)->toBeFalse();
        expect(VerificationCheck::fromContainer(['name' => 'x', 'status' => 'passed', 'report_only' => 1])->reportOnly)->toBeTrue();
    });

    it('drops a non-array data payload', function (): void {
        expect(VerificationCheck::fromContainer(['name' => 'x', 'status' => 'passed', 'data' => 'nope'])->data)->toBe([]);
    });

    it('caps an overlong name and message', function (): void {
        $check = VerificationCheck::fromContainer([
            'name' => str_repeat('a', 500),
            'status' => 'failed',
            'message' => str_repeat('b', 5000),
        ]);

        expect(mb_strlen($check->name))->toBe(100);
        expect(mb_strlen((string) $check->message))->toBe(2000);
    });
});

describe('helpers', function (): void {
    it('reports enforcing status from report_only', function (): void {
        expect(new VerificationCheck('x', VerificationCheckStatus::Passed, false, null)->isEnforcing())->toBeTrue();
        expect(new VerificationCheck('x', VerificationCheckStatus::Passed, true, null)->isEnforcing())->toBeFalse();
    });

    it('reports passed and failed from status', function (): void {
        $passed = new VerificationCheck('x', VerificationCheckStatus::Passed, false, null);
        $failed = new VerificationCheck('x', VerificationCheckStatus::Failed, false, null);

        expect($passed->passed())->toBeTrue();
        expect($passed->failed())->toBeFalse();
        expect($failed->failed())->toBeTrue();
        expect($failed->passed())->toBeFalse();
    });

    it('resolves the display label and description from the check name', function (): void {
        $known = new VerificationCheck('archive_extraction', VerificationCheckStatus::Passed, false, null);
        $unknown = new VerificationCheck('mystery_check', VerificationCheckStatus::Passed, false, null);

        expect($known->label())->toBe('Archive Extraction');
        expect($known->description())->toContain('unpacked safely');
        expect($unknown->label())->toBe('Mystery Check');
        expect($unknown->description())->toBeNull();
    });

    it('round-trips through toArray', function (): void {
        $check = new VerificationCheck('forbidden_files', VerificationCheckStatus::Failed, true, 'Contains an exe', ['count' => 1]);

        expect($check->toArray())->toBe([
            'name' => 'forbidden_files',
            'status' => 'failed',
            'report_only' => true,
            'message' => 'Contains an exe',
            'data' => ['count' => 1],
        ]);
    });
});
