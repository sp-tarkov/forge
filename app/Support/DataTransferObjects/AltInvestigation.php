<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Support\DataTransferObjects\Concerns\CoercesArrayValues;

/**
 * The result of an alt-account investigation: the suspect, ranked candidates, and run counts.
 */
final readonly class AltInvestigation
{
    use CoercesArrayValues;

    /**
     * @param  list<AltCandidate>  $candidates
     */
    public function __construct(
        public AltSuspect $suspect,
        public array $candidates,
        public int $suspectIpCount,
        public int $excludedNoisyIps,
        public bool $truncated,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            suspect: AltSuspect::fromArray(self::coerceArray($data['suspect'] ?? null)),
            candidates: array_map(
                static fn (mixed $candidate): AltCandidate => AltCandidate::fromArray(self::coerceArray($candidate)),
                array_values(self::coerceArray($data['candidates'] ?? null)),
            ),
            suspectIpCount: self::coerceInt($data['suspect_ip_count'] ?? null),
            excludedNoisyIps: self::coerceInt($data['excluded_noisy_ips'] ?? null),
            truncated: self::coerceBool($data['truncated'] ?? null),
        );
    }

    public function candidateCount(): int
    {
        return count($this->candidates);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'suspect' => $this->suspect->toArray(),
            'candidates' => array_map(static fn (AltCandidate $candidate): array => $candidate->toArray(), $this->candidates),
            'suspect_ip_count' => $this->suspectIpCount,
            'excluded_noisy_ips' => $this->excludedNoisyIps,
            'truncated' => $this->truncated,
        ];
    }
}
