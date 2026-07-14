<?php

declare(strict_types=1);

namespace App\Support;

use Composer\Semver\Intervals;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Str;
use Throwable;

/**
 * Composer-backed version constraint matching, the single source of truth for whether a version satisfies a
 * constraint anywhere in the application.
 */
final class VersionMatcher
{
    /**
     * Determine whether a single version satisfies a constraint, treating unparsable input as a non-match.
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies($version, $constraint);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Return the subset of versions that satisfy the constraint, skipping any that cannot be parsed.
     *
     * @param  array<string>  $versions
     * @return array<int, string>
     */
    public static function satisfiedBy(array $versions, string $constraint): array
    {
        return array_values(array_filter(
            $versions,
            static fn (string $version): bool => self::satisfies($version, $constraint),
        ));
    }

    /**
     * Determine whether two constraints can both be satisfied by at least one common version, treating unparsable
     * input as a non-match.
     */
    public static function intersects(string $constraintA, string $constraintB): bool
    {
        try {
            $parser = new VersionParser;

            return Intervals::haveIntersections(
                $parser->parseConstraints($constraintA),
                $parser->parseConstraints($constraintB),
            );
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Sort versions in ascending order, dropping any that cannot be parsed.
     *
     * @param  array<string>  $versions
     * @return array<int, string>
     */
    public static function sort(array $versions): array
    {
        return Semver::sort(self::parseable($versions));
    }

    /**
     * Sort versions in descending order, dropping any that cannot be parsed.
     *
     * @param  array<string>  $versions
     * @return array<int, string>
     */
    public static function rsort(array $versions): array
    {
        return Semver::rsort(self::parseable($versions));
    }

    /**
     * Determine whether a single version string can be parsed by Composer and is therefore usable in matching.
     */
    public static function isValidVersion(string $version): bool
    {
        try {
            (new VersionParser)->normalize($version);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Determine whether a constraint string is a valid Composer version constraint.
     */
    public static function isValidConstraint(string $constraint): bool
    {
        try {
            // A known-good version is passed so the only thing under test is whether the constraint itself parses.
            Semver::satisfiedBy(['1.0.0'], $constraint);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Produce a human-readable explanation of why a version string cannot be used for dependency matching.
     */
    public static function explainInvalidity(string $version): string
    {
        try {
            $parsed = new Version($version);
        } catch (Throwable) {
            return sprintf('"%s" is not a valid semantic version number.', $version);
        }

        $label = mb_ltrim($parsed->getLabels(), '-+');

        if ($label === '') {
            return sprintf('"%s" cannot be used for dependency matching.', $version);
        }

        $suggestion = sprintf('%d.%d.%d+%s', $parsed->getMajor(), $parsed->getMinor(), $parsed->getPatch(), Str::slug(Str::kebab($label)));

        return sprintf('The "-%s" label in "%s" is valid SemVer but cannot be used for dependency matching. Re-release this version using build metadata after a plus sign instead, for example "%s".', $label, $version, $suggestion);
    }

    /**
     * Reduce a list to the version strings Composer is able to parse, so sorting never aborts on a malformed value.
     *
     * @param  array<string>  $versions
     * @return array<int, string>
     */
    private static function parseable(array $versions): array
    {
        $parser = new VersionParser;

        return array_values(array_filter($versions, static function (string $version) use ($parser): bool {
            try {
                $parser->normalize($version);

                return true;
            } catch (Throwable) {
                return false;
            }
        }));
    }
}
