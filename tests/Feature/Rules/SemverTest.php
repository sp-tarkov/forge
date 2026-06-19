<?php

declare(strict_types=1);

use App\Rules\Semver;

/**
 * Run the Semver rule and return the failure message, or null when validation passed.
 */
function runSemverRule(string $value): ?string
{
    $rule = new Semver;
    $message = null;
    $rule->validate('version', $value, function (string $msg) use (&$message): void {
        $message = $msg;
    });

    return $message;
}

describe('Semver version rule', function (): void {
    it('accepts versions that are both decomposable and Composer-parsable', function (string $version): void {
        expect(runSemverRule($version))->toBeNull();
    })->with(['1.2.3', '4.4.3', '0.0.1', '1.0.0+build', '1.0.0+fika-enhanced', '1.0.0-beta', '1.0.0-RC1']);

    it('rejects versions that cannot be decomposed into components', function (string $version): void {
        expect(runSemverRule($version))->toContain('valid semantic version number');
    })->with(['1.0', 'abc', '']);

    it('rejects valid SemVer that Composer cannot match, steering toward build metadata', function (string $version): void {
        // These all parse as official SemVer (so they decompose) but use pre-release labels Composer rejects, which is
        // exactly the class of versions that previously broke dependency matching.
        expect(runSemverRule($version))->toContain('build metadata');
    })->with(['4.4.1-FikaEnhanced', '1.0.0-spt4.0.13', '2.0.1-hotfix', '1.0.0-4']);
});
