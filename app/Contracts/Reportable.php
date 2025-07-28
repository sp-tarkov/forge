<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for models that can be reported
 *
 * This interface defines the methods that reportable models must implement to provide consistent information for the
 * reporting system to use.
 */
interface Reportable
{
    /**
     * Get a human-readable display name for the reportable model.
     */
    public function getReportableDisplayName(): string;

    /**
     * Get the title of the reportable model.
     */
    public function getReportableTitle(): string;

    /**
     * Get an excerpt of the reportable content for display in notifications. Should return a truncated version of the
     * main content or null if no content. Recommended length: ~15 words with "...", if truncated.
     */
    public function getReportableExcerpt(): ?string;

    /**
     * Get the URL to view the reportable content.
     */
    public function getReportableUrl(): string;
}
