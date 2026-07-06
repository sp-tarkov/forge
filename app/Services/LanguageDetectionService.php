<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DataTransferObjects\LanguageDetectionResult;
use Nitotm\Eld\LanguageDetector;

/**
 * Service for locally detecting the language of a markdown comment body using ELD.
 */
final readonly class LanguageDetectionService
{
    public function __construct(private LanguageDetector $languageDetector) {}

    /**
     * Detect the dominant language of a markdown comment body.
     */
    public function detect(string $markdown): LanguageDetectionResult
    {
        $text = $this->prepareText($markdown);

        if ($this->isTooShort($text)) {
            return new LanguageDetectionResult(
                language: null,
                reliable: false,
                tooShort: true,
                strippedText: $text,
            );
        }

        $result = $this->languageDetector->detect($text);
        $language = $result->language === 'und' ? null : $result->language;

        /** @var array<string, float> $scores */
        $scores = $result->scores();

        return new LanguageDetectionResult(
            language: $language,
            reliable: $language !== null && $result->isReliable(),
            tooShort: false,
            strippedText: $text,
            scores: array_slice($scores, 0, 5, true),
        );
    }

    /**
     * Strip markdown syntax and other detection noise from a comment body, leaving only prose.
     */
    private function prepareText(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? '';
        $text = preg_replace('/~~~.*?~~~/s', ' ', $text) ?? '';
        $text = preg_replace('/```.*$/s', ' ', $text) ?? '';
        $text = preg_replace('/`[^`\n]*`/', ' ', $text) ?? '';
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? '';
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? '';
        $text = preg_replace('/^[ \t]*(?:#{1,6}[ \t]*|>+[ \t]*|[-*+][ \t]+|\d+\.[ \t]+)/m', '', $text) ?? '';
        $text = preg_replace('/@[A-Za-z0-9_.-]+/', ' ', $text) ?? '';
        $text = strip_tags($text);
        $text = $this->languageDetector->cleanText($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_trim($text);
    }

    /**
     * Determine whether the stripped text is too short for a meaningful detection. Text containing non-Latin script
     * is never considered too short, since even a couple of CJK or Cyrillic characters signal a non-English comment.
     */
    private function isTooShort(string $text): bool
    {
        if ($this->containsNonLatinScript($text)) {
            return false;
        }

        $letterCount = preg_match_all('/\pL/u', $text);

        return $letterCount === false || $letterCount < config()->integer('comments.translation.min_length', 15);
    }

    /**
     * Determine whether the text contains characters from a non-Latin script.
     */
    private function containsNonLatinScript(string $text): bool
    {
        return preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Cyrillic}\p{Arabic}\p{Hebrew}\p{Thai}\p{Greek}\p{Devanagari}]/u', $text) === 1;
    }
}
