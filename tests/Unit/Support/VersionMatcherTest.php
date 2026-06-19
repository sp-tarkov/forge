<?php

declare(strict_types=1);

use App\Support\VersionMatcher;

describe('VersionMatcher::satisfies', function (): void {
    it('matches versions that satisfy the constraint', function (): void {
        expect(VersionMatcher::satisfies('4.4.3', '~4.4.3'))->toBeTrue()
            ->and(VersionMatcher::satisfies('4.4.4', '~4.4.3'))->toBeTrue()
            ->and(VersionMatcher::satisfies('2.0.0', '^2.0.0'))->toBeTrue();
    });

    it('rejects versions that do not satisfy the constraint', function (): void {
        expect(VersionMatcher::satisfies('4.5.0', '~4.4.3'))->toBeFalse()
            ->and(VersionMatcher::satisfies('1.1.0', '~1.0.0'))->toBeFalse();
    });

    it('treats an unparsable version as a non-match instead of throwing', function (): void {
        // "4.4.1-FikaEnhanced" is valid SemVer but not a Composer-recognized stability label, so Composer throws on
        // it. The facade must absorb that so one malformed stored version cannot crash the comparison.
        expect(VersionMatcher::satisfies('4.4.1-FikaEnhanced', '~4.4.3'))->toBeFalse()
            ->and(VersionMatcher::satisfies('not-a-version', '*'))->toBeFalse();
    });

    it('treats an unparsable constraint as a non-match instead of throwing', function (): void {
        expect(VersionMatcher::satisfies('1.0.0', 'not-a-constraint'))->toBeFalse();
    });
});

describe('VersionMatcher::satisfiedBy', function (): void {
    it('returns only the matching versions', function (): void {
        expect(VersionMatcher::satisfiedBy(['4.4.2', '4.4.3', '4.5.0'], '~4.4.3'))->toBe(['4.4.3']);
    });

    it('skips unparsable versions but keeps the parsable matches', function (): void {
        // The malformed sibling must not poison the batch: the genuine match still comes through.
        expect(VersionMatcher::satisfiedBy(['4.4.1-FikaEnhanced', '4.4.3'], '~4.4.3'))->toBe(['4.4.3']);
    });

    it('returns an empty list when nothing matches', function (): void {
        expect(VersionMatcher::satisfiedBy(['1.0.0', '2.0.0'], '~4.4.3'))->toBe([]);
    });
});

describe('VersionMatcher sorting', function (): void {
    it('sorts ascending and descending', function (): void {
        expect(VersionMatcher::sort(['2.0.0', '1.0.0', '1.5.0']))->toBe(['1.0.0', '1.5.0', '2.0.0'])
            ->and(VersionMatcher::rsort(['2.0.0', '1.0.0', '1.5.0']))->toBe(['2.0.0', '1.5.0', '1.0.0']);
    });

    it('drops unparsable versions instead of throwing', function (): void {
        expect(VersionMatcher::sort(['2.0.0', '4.4.1-FikaEnhanced', '1.0.0']))->toBe(['1.0.0', '2.0.0']);
    });
});

describe('VersionMatcher validity checks', function (): void {
    it('recognizes Composer-parsable versions', function (): void {
        expect(VersionMatcher::isValidVersion('4.4.3'))->toBeTrue()
            ->and(VersionMatcher::isValidVersion('1.2.3+fika-enhanced'))->toBeTrue()
            ->and(VersionMatcher::isValidVersion('4.4.1-FikaEnhanced'))->toBeFalse()
            ->and(VersionMatcher::isValidVersion('not-a-version'))->toBeFalse();
    });

    it('recognizes valid and invalid constraints', function (): void {
        expect(VersionMatcher::isValidConstraint('~4.4.3'))->toBeTrue()
            ->and(VersionMatcher::isValidConstraint('^2.0 || >=3.1'))->toBeTrue()
            ->and(VersionMatcher::isValidConstraint('not-a-constraint'))->toBeFalse();
    });
});

describe('VersionMatcher::explainInvalidity', function (): void {
    it('steers well-formed SemVer with a bad label toward build metadata', function (): void {
        $reason = VersionMatcher::explainInvalidity('4.4.1-FikaEnhanced');

        expect($reason)->toContain('build metadata')
            ->and($reason)->toContain('4.4.1+fika-enhanced');
    });

    it('reports non-SemVer values as not a valid version number', function (): void {
        expect(VersionMatcher::explainInvalidity('not-a-version'))->toContain('not a valid semantic version number');
    });
});
