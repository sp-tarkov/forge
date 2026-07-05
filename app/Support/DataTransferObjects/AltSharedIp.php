<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Support\DataTransferObjects\Concerns\CoercesArrayValues;

/**
 * A single IP address shared between the suspect and a candidate.
 */
final readonly class AltSharedIp
{
    use CoercesArrayValues;

    /**
     * @param  list<string>  $sources
     * @param  list<string>  $otherAccounts
     */
    public function __construct(
        public string $ip,
        public int $breadth,
        public int $hits,
        public array $sources,
        public string $firstSeen,
        public string $lastSeen,
        public array $otherAccounts,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ip: self::coerceString($data['ip'] ?? null),
            breadth: self::coerceInt($data['breadth'] ?? null),
            hits: self::coerceInt($data['hits'] ?? null),
            sources: self::coerceStringList($data['sources'] ?? null),
            firstSeen: self::coerceString($data['first_seen'] ?? null),
            lastSeen: self::coerceString($data['last_seen'] ?? null),
            otherAccounts: self::coerceStringList($data['other_accounts'] ?? null),
        );
    }

    /**
     * @return array{ip: string, breadth: int, hits: int, sources: list<string>, first_seen: string, last_seen: string, other_accounts: list<string>}
     */
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'breadth' => $this->breadth,
            'hits' => $this->hits,
            'sources' => $this->sources,
            'first_seen' => $this->firstSeen,
            'last_seen' => $this->lastSeen,
            'other_accounts' => $this->otherAccounts,
        ];
    }
}
