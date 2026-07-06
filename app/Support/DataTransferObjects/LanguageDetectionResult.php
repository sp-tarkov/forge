<?php

declare(strict_types=1);

namespace App\Support\DataTransferObjects;

/**
 * Value object representing the outcome of a local (on-server) language detection pass.
 */
final readonly class LanguageDetectionResult
{
    /**
     * @param  array<string, float>  $scores
     */
    public function __construct(
        public ?string $language,
        public bool $reliable,
        public bool $tooShort,
        public string $strippedText,
        public array $scores = [],
    ) {}

    /**
     * Determine whether the text was reliably detected as English.
     */
    public function isConfidentEnglish(): bool
    {
        return $this->reliable && $this->language === 'en';
    }
}
