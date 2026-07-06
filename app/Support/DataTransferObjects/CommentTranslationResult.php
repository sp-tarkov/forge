<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * Value object representing the outcome of a comment translation request.
 */
final readonly class CommentTranslationResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $detectedLanguage,
        public ?string $translatedBody,
        public array $metadata = [],
    ) {}

    /**
     * Determine whether the translator failed to produce a usable result.
     */
    public function isError(): bool
    {
        return isset($this->metadata['error']);
    }
}
