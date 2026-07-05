<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Support\DataTransferObjects\Concerns\CoercesArrayValues;

/**
 * The strongest activity-timing relationship between a candidate and the suspect on a shared IP.
 */
final readonly class AltTimeline
{
    use CoercesArrayValues;

    public function __construct(
        public string $type,
        public int $gapSeconds,
        public string $gapHuman,
        public string $ip,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: self::coerceString($data['type'] ?? null),
            gapSeconds: self::coerceInt($data['gap_seconds'] ?? null),
            gapHuman: self::coerceString($data['gap_human'] ?? null),
            ip: self::coerceString($data['ip'] ?? null),
        );
    }

    /**
     * @return array{type: string, gap_seconds: int, gap_human: string, ip: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'gap_seconds' => $this->gapSeconds,
            'gap_human' => $this->gapHuman,
            'ip' => $this->ip,
        ];
    }
}
