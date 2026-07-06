<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\DataTransferObjects\CommentTranslationResult;

interface CommentTranslator
{
    /**
     * Determine whether the translator has the credentials it needs to make API requests.
     */
    public function isConfigured(): bool;

    /**
     * Detect the language of a comment body and translate it into English when needed.
     */
    public function translate(string $body): CommentTranslationResult;

    /**
     * Submit a bulk set of comment bodies, keyed by a caller-provided ID, for asynchronous translation. Returns the
     * provider batch ID, or null when there was nothing to submit.
     *
     * @param  array<int|string, string>  $bodies
     */
    public function submitBatch(array $bodies): ?string;

    /**
     * Determine whether an asynchronous translation batch has finished processing.
     */
    public function isBatchComplete(string $batchId): bool;

    /**
     * Fetch the results of a completed translation batch, keyed by the caller-provided ID.
     *
     * @return array<string, CommentTranslationResult>
     */
    public function getBatchResults(string $batchId): array;
}
