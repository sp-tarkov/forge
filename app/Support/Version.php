<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\InvalidVersionNumberException;
use Illuminate\Support\Str;
use Stringable;

class Version implements Stringable
{
    /**
     * Parse a semantic version number.
     *
     * @param  int|string  $version  The version number to parse.
     *
     * @throws InvalidVersionNumberException
     */
    public function __construct(
        private int|string $version,
        private int $major = 0,
        private int $minor = 0,
        private int $patch = 0,
        private string $labels = '',
    ) {
        $this->version = Str::ltrim($this->version, 'v');

        $this->parse();
    }

    /**
     * Create a Version instance specifically for the format of the imported SPT versions from GitHub.
     */
    public static function cleanSptImport(string $version): self
    {
        // Remove leading 'SPT' and trailing build number. EZ.
        $cleanedVersion = preg_replace('/^SPT\s+(\d+\.\d+\.\d+).*/', '$1', $version);

        return new self($cleanedVersion);
    }

    /**
     * Clean user provided version numbers for mod imports.
     *
     * @throws InvalidVersionNumberException
     */
    public static function cleanModImport(string|int $version): self
    {
        $version = Str::trim(Str::ltrim((string) $version, 'v.'));

        // Match the semantic version and capture any additional metadata.
        if (preg_match('/^(?P<preMetadata>.*?)(?P<semver>\d+\.*\d*\.*\d*)(?P<postMetadata>.*)$/', $version, $matches)) {
            $semver = $matches['semver'];

            // Combine and clean metadata.
            $metadata = Str::of($matches['preMetadata'].$matches['postMetadata'])
                ->trim('()[]{}-')
                ->slug()
                ->toString();

            // Ensure that the semver is three parts with no leading zeros.
            $segments = explode('.', $semver);
            $segments = array_pad($segments, 3, '0');
            $semver = implode('.', array_map(fn ($s): string => (string) (int) $s, $segments));

            $cleanedVersion = $metadata ? sprintf('%s+%s', $semver, $metadata) : $semver;
        } else {
            $cleanedVersion = '0.0.0';
        }

        return new self($cleanedVersion);
    }

    /**
     * Parse the version components using SemVer compliant regex.
     *
     * @throws InvalidVersionNumberException
     */
    private function parse(): void
    {
        // Official SemVer regex pattern:
        // https://semver.org/#is-there-a-suggested-regular-expression-regex-to-check-a-semver-string
        $pattern = "/^(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)(?:-(?P<prerelease>(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+(?P<buildMetadata>[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/";

        throw_unless(preg_match($pattern, $this->version, $matches), new InvalidVersionNumberException('Invalid SemVer: '.$this->version));

        $this->major = (int) $matches['major'];
        $this->minor = (int) $matches['minor'];
        $this->patch = (int) $matches['patch'];

        $labels = '';
        if (! empty($matches['prerelease'])) {
            $labels .= Str::trim('-'.$matches['prerelease']);
        }

        if (! empty($matches['buildMetadata'])) {
            $labels .= Str::trim('+'.$matches['buildMetadata']);
        }

        $this->labels = $labels;
    }

    /**
     * Do the best to guess a SemVer constraint based on the input version. Currently being used to clean the SPT
     * version tag being imported from the Hub and resolve it to an active SPT version. This is a guess and will
     * probably not be accurate; use with great caution. Preferably, not at all.
     */
    public static function guessSemanticConstraint(string|int $version, bool $appendAnyPatch = true): string
    {
        // Match both two-part and three-part semantic versions.
        preg_match_all('/\b\d+\.\d+(?:\.\d+)?\b/', (string) $version, $matches);

        // Get the last version found, if any.
        $version = end($matches[0]) ?: '0.0.0';

        if (! $appendAnyPatch) {
            return $version;
        }

        // If version is two-part (e.g., "3.9"), prefix with "~"
        if (preg_match('/^\d+\.\d+$/', $version)) {
            $version = '~'.$version.'.0';
        }

        return $version;
    }

    /**
     * Get the major version number.
     */
    public function getMajor(): int
    {
        return $this->major;
    }

    /**
     * Get the minor version number.
     */
    public function getMinor(): int
    {
        return $this->minor;
    }

    /**
     * Get the patch version number.
     */
    public function getPatch(): int
    {
        return $this->patch;
    }

    /**
     * Get the labels (prerelease and metadata).
     */
    public function getLabels(): string
    {
        return $this->labels;
    }

    /**
     * Get the normalized version string.
     */
    public function __toString(): string
    {
        return $this->getVersion();
    }

    /**
     * Get the normalized version string.
     */
    public function getVersion(): string
    {
        $version = $this->major.'.'.$this->minor.'.'.$this->patch;

        if (! empty($this->labels)) {
            // Check if labels contain build metadata (indicated by +).
            if (Str::contains($this->labels, '+')) {
                $labelParts = explode('+', $this->labels, 2);
                $prerelease = $labelParts[0];
                $buildMetaData = $labelParts[1];

                if (! empty($prerelease)) {
                    $version .= '-'.$prerelease;
                }

                $version .= '+'.$buildMetaData;
            } else {
                // No build metadata, treat entire labels as pre-release.
                $version .= '-'.$this->labels;
            }
        }

        return $version;
    }
}
