<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Support\DataTransferObjects\Concerns\CoercesArrayValues;

/**
 * A candidate alternate account, scored and with its supporting evidence.
 */
final readonly class AltCandidate
{
    use CoercesArrayValues;

    /**
     * @param  list<string>  $matchedSignals
     * @param  list<AltSharedIp>  $sharedIps
     * @param  list<string>  $fingerprintOverlap
     */
    public function __construct(
        public int $userId,
        public string $name,
        public ?string $email,
        public ?string $profileUrl,
        public ?string $createdAt,
        public bool $deleted,
        public int $score,
        public array $matchedSignals,
        public array $sharedIps,
        public ?string $domain,
        public bool $sameDomain,
        public bool $disposableDomain,
        public ?AltTimeline $timeline,
        public array $fingerprintOverlap,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $timeline = $data['timeline'] ?? null;

        return new self(
            userId: self::coerceInt($data['user_id'] ?? null),
            name: self::coerceString($data['name'] ?? null),
            email: self::coerceNullableString($data['email'] ?? null),
            profileUrl: self::coerceNullableString($data['profile_url'] ?? null),
            createdAt: self::coerceNullableString($data['created_at'] ?? null),
            deleted: self::coerceBool($data['deleted'] ?? null),
            score: self::coerceInt($data['score'] ?? null),
            matchedSignals: self::coerceStringList($data['matched_signals'] ?? null),
            sharedIps: array_map(
                static fn (mixed $sharedIp): AltSharedIp => AltSharedIp::fromArray(self::coerceArray($sharedIp)),
                array_values(self::coerceArray($data['shared_ips'] ?? null)),
            ),
            domain: self::coerceNullableString($data['domain'] ?? null),
            sameDomain: self::coerceBool($data['same_domain'] ?? null),
            disposableDomain: self::coerceBool($data['disposable_domain'] ?? null),
            timeline: is_array($timeline) ? AltTimeline::fromArray($timeline) : null,
            fingerprintOverlap: self::coerceStringList($data['fingerprint_overlap'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'profile_url' => $this->profileUrl,
            'created_at' => $this->createdAt,
            'deleted' => $this->deleted,
            'score' => $this->score,
            'matched_signals' => $this->matchedSignals,
            'shared_ips' => array_map(static fn (AltSharedIp $sharedIp): array => $sharedIp->toArray(), $this->sharedIps),
            'domain' => $this->domain,
            'same_domain' => $this->sameDomain,
            'disposable_domain' => $this->disposableDomain,
            'timeline' => $this->timeline?->toArray(),
            'fingerprint_overlap' => $this->fingerprintOverlap,
        ];
    }
}
