<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * An account's device fingerprint: the full user-agent strings it presented, and version-independent prints
 * (platform, browser, and language) that stay stable as the browser auto-updates over time.
 */
final readonly class AltFingerprint
{
    /**
     * @param  list<string>  $agents
     * @param  list<string>  $prints
     */
    public function __construct(
        public array $agents,
        public array $prints,
    ) {}
}
