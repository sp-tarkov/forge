<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Trackable;
use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for tracking user events and analytics.
 */
#[Singleton]
class TrackService
{
    /**
     * Create a new TrackService instance.
     */
    public function __construct(
        /**
         * The geolocation service for IP-based location lookups.
         */
        protected GeolocationService $geolocationService
    ) {}

    /**
     * Track a user action or visitor event.
     *
     * Records comprehensive event data including user information, request details, device/browser information,
     * geolocation data, and any additional custom data. This method captures both authenticated and anonymous user
     * actions when triggered. The actual tracking is deferred to avoid blocking the response.
     *
     * @param  array<string, mixed>  $additionalData
     */
    public function event(TrackingEventType $eventType, ?Model $trackable = null, array $additionalData = []): void
    {
        defer(function () use ($eventType, $trackable, $additionalData): void {
            try {
                $this->createEvent($eventType, $trackable, $additionalData);
            } catch (Throwable $throwable) {
                Log::error('Failed to track event', [
                    'event_type' => $eventType->value,
                    'trackable_type' => $trackable?->getMorphClass(),
                    'trackable_id' => $trackable?->getKey(),
                    'error' => $throwable->getMessage(),
                    'trace' => $throwable->getTraceAsString(),
                ]);
            }
        });
    }

    /**
     * Create a tracking event synchronously and return it.
     * Use this when you need the TrackingEvent ID immediately (e.g., for linking to reports).
     *
     * @param  array<string, mixed>  $additionalData
     */
    public function eventSync(
        TrackingEventType $eventType,
        ?Model $trackable = null,
        array $additionalData = [],
        bool $isModerationAction = false,
        ?string $reason = null
    ): TrackingEvent {
        return $this->createEvent($eventType, $trackable, $additionalData, $isModerationAction, $reason);
    }

    /**
     * Get visitor data
     *
     * @return array<string, string|null>
     */
    private function getVisitorData(): array
    {
        try {
            $visitor = visitor();

            return [
                'device' => $visitor->device(),
                'platform' => $visitor->platform(),
                'browser' => $visitor->browser(),
            ];
        } catch (Throwable $throwable) {
            Log::warning('Visitor data extraction failed during tracking', [
                'error' => $throwable->getMessage(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'device' => null,
                'platform' => null,
                'browser' => null,
            ];
        }
    }

    /**
     * Extract contextual data for the event based on the event type and trackable model.
     *
     * @param  array<string, mixed>  $additionalData
     * @return array<string, mixed>
     */
    private function extractEventData(TrackingEventType $eventType, ?Model $trackable, array $additionalData): array
    {
        $eventData = $additionalData;

        if ($trackable instanceof Trackable) {
            $eventData['snapshot'] = $trackable->getTrackingSnapshot();
            $eventData['url'] = $trackable->getTrackingUrl();
        }

        return $eventData;
    }

    /**
     * Create and persist a tracking event record.
     *
     * @param  array<string, mixed>  $additionalData
     */
    private function createEvent(
        TrackingEventType $eventType,
        ?Model $trackable,
        array $additionalData,
        bool $isModerationAction = false,
        ?string $reason = null
    ): TrackingEvent {
        $request = request();
        $visitorData = $this->getVisitorData();

        // Get location data for the current IP
        $ip = $request->ip();
        $locationData = [];
        if ($ip) {
            $locationData = $this->geolocationService->getLocationFromIP($ip);
        }

        // Extract contextual information based on the event type and trackable model
        $eventData = $this->extractEventData($eventType, $trackable, $additionalData);

        // For authentication events (login, logout, register), the user is both the visitor and visitable
        $visitorId = Auth::id();
        $visitorType = Auth::check() ? Auth::user()::class : null;

        // For events where trackable user should be the visitor
        // - Authentication events: Auth might be cleared (logout and account deletion)
        // - Ban received events: Track from the banned user's perspective (not the moderator's)
        $userAsVisitorEvents = [
            TrackingEventType::LOGIN,
            TrackingEventType::LOGOUT,
            TrackingEventType::REGISTER,
            TrackingEventType::ACCOUNT_DELETE,
            TrackingEventType::USER_BANNED,
            TrackingEventType::USER_UNBANNED,
        ];
        if (in_array($eventType, $userAsVisitorEvents) && $trackable instanceof User) {
            $visitorId = $trackable->getKey();
            $visitorType = $trackable->getMorphClass();
        }

        // Record the event
        return TrackingEvent::query()->create([
            'event_name' => $eventType->value,
            'event_data' => $eventData,
            'is_moderation_action' => $isModerationAction,
            'reason' => $reason,
            'url' => $request->getPathInfo(),
            'referer' => $request->header('referer'),
            'languages' => $request->getLanguages(),
            'useragent' => $request->userAgent(),
            'device' => $visitorData['device'],
            'platform' => $visitorData['platform'],
            'browser' => $visitorData['browser'],
            'ip' => $ip,
            'visitable_type' => $trackable?->getMorphClass(),
            'visitable_id' => $trackable?->getKey(),
            'visitor_type' => $visitorType,
            'visitor_id' => $visitorId,
            'country_code' => $locationData['country_code'] ?? null,
            'country_name' => $locationData['country_name'] ?? null,
            'region_name' => $locationData['region_name'] ?? null,
            'city_name' => $locationData['city_name'] ?? null,
            'latitude' => $locationData['latitude'] ?? null,
            'longitude' => $locationData['longitude'] ?? null,
            'timezone' => $locationData['timezone'] ?? null,
        ]);
    }
}
