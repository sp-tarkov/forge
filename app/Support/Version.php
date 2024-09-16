<?php

namespace App\Support;

use App\Exceptions\InvalidVersionNumberException;

class Version
{
    protected int $major = 0;

    protected int $minor = 0;

    protected int $patch = 0;

    protected string $preRelease = '';

    protected string $version;

    /**
     * Constructor.
     *
     * @throws InvalidVersionNumberException
     */
    public function __construct(string $version)
    {
        $this->version = $version;
        $this->parseVersion();
    }

    /**
     * Parse the version string into its components.
     *
     * @throws InvalidVersionNumberException
     */
    protected function parseVersion(): void
    {
        $matches = [];

        // Regex to match semantic versioning, including pre-release identifiers
        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([\w.-]+))?$/', $this->version, $matches)) {
            $this->major = (int) $matches[1];
            $this->minor = (int) ($matches[2] ?? 0);
            $this->patch = (int) ($matches[3] ?? 0);
            $this->preRelease = $matches[4] ?? '';
        } else {
            throw new InvalidVersionNumberException('Invalid version number: '.$this->version);
        }
    }

    public function getMajor(): int
    {
        return $this->major;
    }

    public function getMinor(): int
    {
        return $this->minor;
    }

    public function getPatch(): int
    {
        return $this->patch;
    }

    public function getPreRelease(): string
    {
        return $this->preRelease;
    }

    public function __toString(): string
    {
        return $this->version;
    }
}
