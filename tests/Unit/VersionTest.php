<?php

declare(strict_types=1);

use App\Support\Version;

test('versions can be parsed', function (): void {
    $versions = [
        // These should all be valid Semantic Versions
        '1.0.0' => [1, 0, 0, ''],
        '1.0.0-alpha' => [1, 0, 0, '-alpha'],
        '1.0.0-alpha.1' => [1, 0, 0, '-alpha.1'],
        '1.0.0-0.3.7' => [1, 0, 0, '-0.3.7'],
        '1.0.0-x.7.z.92' => [1, 0, 0, '-x.7.z.92'],
        '1.0.0-x.7.z.92+meta' => [1, 0, 0, '-x.7.z.92+meta'],
        '1.0.0+meta' => [1, 0, 0, '+meta'],
        '1.0.0+meta-valid' => [1, 0, 0, '+meta-valid'],
        '1.0.0-rc.1+build.1' => [1, 0, 0, '-rc.1+build.1'],
        '1.0.0-beta+exp.sha.5114f85' => [1, 0, 0, '-beta+exp.sha.5114f85'],
        '1.0.0+21AF26D3--117B344092BD' => [1, 0, 0, '+21AF26D3--117B344092BD'],
        '1.0.0-0A.12.0' => [1, 0, 0, '-0A.12.0'],
        '1.0.0-0A.12.0+meta' => [1, 0, 0, '-0A.12.0+meta'],
        '1.0.0-0A.12.0+meta-valid' => [1, 0, 0, '-0A.12.0+meta-valid'],
        '1.0.0-0A.12.0-rc.1+build.1' => [1, 0, 0, '-0A.12.0-rc.1+build.1'],
        'v1.0.0-0A.12.0-rc.1+build.1' => [1, 0, 0, '-0A.12.0-rc.1+build.1'], // Should handle leading 'v'
    ];

    foreach ($versions as $version => $properties) {
        $version = new Version($version);

        expect($version->getMajor())->toBe($properties[0])
            ->and($version->getMinor())->toBe($properties[1])
            ->and($version->getPatch())->toBe($properties[2])
            ->and($version->getLabels())->toBe($properties[3]);
    }
});

test('imported spt versions can be cleaned properly', function (): void {
    $versions = [
        'SPT 1.2.3' => '1.2.3',
        'SPT 1.2.3 (123456)' => '1.2.3',
    ];
    foreach ($versions as $version => $clean) {
        $v = Version::cleanSptImport($version);
        expect($v->getVersion())->toBe($clean);
    }
});

test('imported mod spt version constraints can be guessed properly', function (): void {
    $versions = [
        'SPT 3.11' => '~3.11.0',
        'SPT 3.10' => '~3.10.0',
        'SPT 3.9' => '~3.9.0',
        'SPT 3.8' => '~3.8.0',
        'SPT 3.7' => '~3.7.0',
        'SPT 3.4-3.6' => '~3.6.0',
        'SPT 3.0-3.3' => '~3.3.0',
        'SPT 2.3' => '~2.3.0',
        'Outdated' => '0.0.0',
    ];
    foreach ($versions as $version => $clean) {
        $v = Version::guessSemanticConstraint($version);
        expect($v)->toBe($clean);
    }
});

test('imported mod versions can be parsed after cleaning', function (): void {
    $versions = [
        '1' => '1.0.0',
        '1.2' => '1.2.0',
        '1.5.0 (SPT 3.11)' => '1.5.0+spt-311',
        '1.123.01' => '1.123.1',
        '1.012.12' => '1.12.12',
        '01.123.12' => '1.123.12',
        '1.1.0-spt371' => '1.1.0+spt371',
        '13.9.1.27928' => '13.9.1+27928',
        '4.0.0(for r7 & r7.1)' => '4.0.0+for-r7-r71',
        '1.8 AKI8' => '1.8.0+aki8',
        '1.1 - Simple' => '1.1.0+simple',
        'Beta 1.9' => '1.9.0+beta',
    ];

    foreach ($versions as $version => $clean) {
        $v = Version::cleanModImport($version);
        expect($v->getVersion())->toBe($clean);
    }
});
