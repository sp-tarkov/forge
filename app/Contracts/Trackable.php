<?php

declare(strict_types=1);

namespace App\Contracts;

interface Trackable
{
    /**
     * Get the URL to view this trackable resource.
     */
    public function getTrackingUrl(): string;

    /**
     * Get the display title for this trackable resource.
     */
    public function getTrackingTitle(): string;

    /**
     * Get the snapshot data to store for this trackable resource. This data is used to historically preserve context
     * even if the model is later modified or deleted.
     *
     * @return array<string, mixed>
     */
    public function getTrackingSnapshot(): array;

    /**
     * Get contextual information about this trackable resource. Typically used for display in the activity timeline.
     */
    public function getTrackingContext(): ?string;
}
