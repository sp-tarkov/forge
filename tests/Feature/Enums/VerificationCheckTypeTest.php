<?php

declare(strict_types=1);

use App\Enums\VerificationCheckType;

describe('labelFor', function (): void {
    it('resolves the label for a known check name', function (): void {
        expect(VerificationCheckType::labelFor('archive_extraction'))->toBe('Archive Extraction');
    });

    it('resolves the labels for the dll checks', function (): void {
        expect(VerificationCheckType::labelFor('dll_guid_match'))->toBe('GUID Match');
        expect(VerificationCheckType::labelFor('dll_version_match'))->toBe('Version Match');
    });

    it('humanizes an unknown check name', function (): void {
        expect(VerificationCheckType::labelFor('manifest_present'))->toBe('Manifest Present');
    });
});

describe('descriptionFor', function (): void {
    it('resolves the description for a known check name', function (): void {
        expect(VerificationCheckType::descriptionFor('archive_extraction'))
            ->toBe(VerificationCheckType::ArchiveExtraction->description());
    });

    it('returns null for an unknown check name', function (): void {
        expect(VerificationCheckType::descriptionFor('manifest_present'))->toBeNull();
    });
});

describe('presentation', function (): void {
    it('offers a non-empty label and description for every case', function (VerificationCheckType $type): void {
        expect($type->label())->toBeString()->not->toBeEmpty();
        expect($type->description())->toBeString()->not->toBeEmpty();
    })->with(VerificationCheckType::cases());
});
