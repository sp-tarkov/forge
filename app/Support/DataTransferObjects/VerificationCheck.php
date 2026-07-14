<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

use App\Enums\VerificationCheckStatus;

/**
 * Value object representing a single check within a verification run, built from untrusted container output.
 */
final readonly class VerificationCheck
{
    private const int MAX_NAME_LENGTH = 100;

    private const int MAX_MESSAGE_LENGTH = 2000;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $name,
        public VerificationCheckStatus $status,
        public bool $reportOnly,
        public ?string $message,
        public array $data = [],
    ) {}

    /**
     * Build a check from a raw container entry, coercing and bounding every field because the container output is
     * untrusted. An unrecognized status resolves to a failure so a malformed entry cannot silently pass.
     *
     * @param  array<string, mixed>  $entry
     */
    public static function fromContainer(array $entry): self
    {
        $name = is_string($entry['name'] ?? null) ? mb_substr($entry['name'], 0, self::MAX_NAME_LENGTH) : 'unknown';

        $message = is_string($entry['message'] ?? null) && $entry['message'] !== ''
            ? mb_substr($entry['message'], 0, self::MAX_MESSAGE_LENGTH)
            : null;

        $rawData = $entry['data'] ?? null;
        /** @var array<string, mixed> $data */
        $data = is_array($rawData) ? $rawData : [];

        return new self(
            name: $name === '' ? 'unknown' : $name,
            status: VerificationCheckStatus::fromContainer(is_string($entry['status'] ?? null) ? $entry['status'] : null),
            reportOnly: (bool) ($entry['report_only'] ?? false),
            message: $message,
            data: $data,
        );
    }

    /**
     * Whether this check counts toward the overall verification outcome. Report-only checks are recorded but never
     * fail the verification, so a new validator can be trialled against real uploads before it is enforced.
     */
    public function isEnforcing(): bool
    {
        return ! $this->reportOnly;
    }

    /**
     * Whether the check passed.
     */
    public function passed(): bool
    {
        return $this->status === VerificationCheckStatus::Passed;
    }

    /**
     * Whether the check failed.
     */
    public function failed(): bool
    {
        return $this->status === VerificationCheckStatus::Failed;
    }

    /**
     * Convert the check to a plain array for JSON storage.
     *
     * @return array{name: string, status: string, report_only: bool, message: string|null, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'report_only' => $this->reportOnly,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
