<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Trackable;
use App\Enums\TrackingEventType;
use Database\Factories\TrackingEventFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $event_name
 * @property array<string, mixed>|null $event_data
 * @property string|null $url
 * @property string|null $referer
 * @property array<int, string>|null $languages
 * @property string|null $useragent
 * @property string|null $device
 * @property string|null $platform
 * @property string|null $browser
 * @property string|null $ip
 * @property int|null $visitable_id
 * @property string|null $visitable_type
 * @property int|null $visitor_id
 * @property string|null $visitor_type
 * @property string|null $country_code
 * @property string|null $country_name
 * @property string|null $region_name
 * @property string|null $city_name
 * @property null|string $latitude
 * @property null|string $longitude
 * @property string|null $timezone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read null|string $event_context attribute
 * @property-read string $event_display_name attribute
 * @property-read null|string $event_url attribute
 * @property Model $trackable
 * @property User|null $user
 */
class TrackingEvent extends Model
{
    /** @use HasFactory<TrackingEventFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'languages' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    /**
     * Get the authenticated user who performed this action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }

    /**
     * Get the model instance that this tracking event is associated with. This relationship allows tracking events to
     * be linked to any model that was involved in the event.
     *
     * @return MorphTo<Model, $this>
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo('visitable');
    }

    /**
     * Get the TrackingEventType enum instance for this event.
     */
    public function getEventType(): ?TrackingEventType
    {
        return $this->event_name ? TrackingEventType::tryFrom($this->event_name) : null;
    }

    /**
     * Get the user-friendly display name for this event.
     *
     * @return Attribute<string, never>
     */
    protected function eventDisplayName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (! $this->event_name) {
                    return 'Page visit';
                }

                $eventType = $this->getEventType();
                if ($eventType !== null) {
                    return $eventType->getName();
                }

                // Fallback for unknown event types
                return ucfirst(str_replace('_', ' ', $this->event_name));
            }
        );
    }

    /**
     * Get contextual information about this tracking event.
     *
     * Returns details that provide context about what was tracked, such as comment snippets, model names, etc...
     *
     * @return Attribute<string|null, never>
     */
    protected function eventContext(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $eventType = $this->getEventType();

                // For events without a type, show URL
                if ($eventType === null) {
                    return $this->url ?? null;
                }

                // Try the trackable model first (only if we have valid polymorphic data)
                $trackable = null;
                if ($this->visitable_type && $this->visitable_id) {
                    $trackable = $this->trackable()->first();
                }

                if ($trackable instanceof Trackable) {
                    $context = $trackable->getTrackingContext();
                    if ($context) {
                        return $context;
                    }
                }

                // Fallback to snapshot data from event_data
                if (isset($this->event_data['snapshot']) || $this->event_data) {
                    $context = $this->getContextFromSnapshot();
                    if ($context) {
                        return $context;
                    }
                }

                // Final fallback to URL
                return $this->url ?? null;
            }
        );
    }

    /**
     * Get the URL associated with this tracking event.
     *
     * Returns a URL that's relevant to the tracked event, such as a link
     * to the model that was interacted with or the original page URL.
     *
     * @return Attribute<string|null, never>
     */
    protected function eventUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $eventType = $this->getEventType();

                // For events that shouldn't show URLs, return null
                if (! $eventType || ! $eventType->shouldShowUrl()) {
                    return null;
                }

                // Try trackable model first (only if we have valid polymorphic data)
                $trackable = null;
                if ($this->visitable_type && $this->visitable_id) {
                    $trackable = $this->trackable()->first();
                }

                if ($trackable instanceof Trackable) {
                    return $trackable->getTrackingUrl();
                }

                // Final fallback to request URL
                return $this->event_data['url'] ?? $this->url ? url($this->url) : null;
            }
        );
    }

    /**
     * Get context information from snapshot data.
     */
    private function getContextFromSnapshot(): ?string
    {
        $snapshot = $this->event_data['snapshot'] ?? [];

        // Return the most relevant context based on what's available
        return $snapshot['comment_body'] ??
               $snapshot['mod_name'] ??
               $snapshot['version_name'] ??
               null;
    }
}
