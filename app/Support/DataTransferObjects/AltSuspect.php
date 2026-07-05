<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Support\DataTransferObjects\Concerns\CoercesArrayValues;

/**
 * The account being investigated.
 */
final readonly class AltSuspect
{
    use CoercesArrayValues;

    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $domain,
        public bool $disposableDomain,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::coerceInt($data['id'] ?? null),
            name: self::coerceString($data['name'] ?? null),
            email: self::coerceString($data['email'] ?? null),
            domain: self::coerceNullableString($data['domain'] ?? null),
            disposableDomain: self::coerceBool($data['disposable_domain'] ?? null),
        );
    }

    /**
     * @return array{id: int, name: string, email: string, domain: string|null, disposable_domain: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'domain' => $this->domain,
            'disposable_domain' => $this->disposableDomain,
        ];
    }
}
