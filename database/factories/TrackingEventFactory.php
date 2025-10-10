<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\Trackable;
use App\Enums\TrackingEventType;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Factory for creating realistic tracking event test data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrackingEvent>
 */
class TrackingEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TrackingEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventType = $this->faker->randomElement(TrackingEventType::cases());
        $isAuthenticated = $this->faker->boolean(70); // 70% chance of authenticated user

        $data = [
            'event_name' => $eventType->value,
            'url' => $this->faker->randomElement([
                '/mods',
                '/mods/create',
                '/dashboard',
                '/profile',
                '/api/mods',
                '/search',
            ]),
            'referer' => $this->faker->optional(0.6)->url(),
            'languages' => [$this->faker->randomElement(['en-US', 'en-GB', 'es-ES', 'fr-FR', 'de-DE'])],
            'useragent' => $this->faker->userAgent(),
            'device' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
            'platform' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'iOS', 'Android']),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera']),
            'ip' => $this->faker->ipv4(),
            'country_code' => $this->faker->countryCode(),
            'country_name' => $this->faker->country(),
            'region_name' => $this->faker->randomElement([
                'California', 'New York', 'Texas', 'Florida', 'Illinois',
                'Pennsylvania', 'Ohio', 'Georgia', 'North Carolina', 'Michigan',
            ]),
            'city_name' => $this->faker->city(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'timezone' => $this->faker->timezone(),
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ];

        // Add authenticated user data
        if ($isAuthenticated) {
            $data['visitor_type'] = User::class;
            $data['visitor_id'] = User::factory();
        }

        // Add trackable model if event type requires it
        if ($eventType->requiresTrackable()) {
            $trackable = $this->createTrackableModel($eventType);
            if ($trackable instanceof Trackable) {
                $data['visitable_type'] = get_class($trackable);
                $data['visitable_id'] = $trackable->getKey();

                // Generate event data using trait methods
                $data['event_data'] = $this->generateEventDataFromTrackable($eventType, $trackable);
            }
        } else {
            $data['event_data'] = [];
        }

        return $data;
    }

    /**
     * State for creating anonymous visitor events.
     */
    public function anonymous(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'visitor_type' => null,
                'visitor_id' => null,
            ];
        });
    }

    /**
     * State for creating authenticated user events.
     */
    public function authenticated(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'visitor_type' => User::class,
                'visitor_id' => User::factory(),
            ];
        });
    }

    /**
     * State for creating events with specific event type.
     */
    public function eventType(TrackingEventType $eventType): static
    {
        return $this->state(function (array $attributes) use ($eventType) {
            $data = ['event_name' => $eventType->value];

            // Add trackable model if required
            if ($eventType->requiresTrackable()) {
                $trackable = $this->createTrackableModel($eventType);
                if ($trackable instanceof Trackable) {
                    $data['visitable_type'] = get_class($trackable);
                    $data['visitable_id'] = $trackable->getKey();

                    // Generate event data using trait methods
                    $data['event_data'] = $this->generateEventDataFromTrackable($eventType, $trackable);
                }
            } else {
                $data['event_data'] = [];
            }

            return $data;
        });
    }

    /**
     * State for creating recent events (last 30 days).
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            ];
        });
    }

    /**
     * Create a trackable model instance based on the event type.
     */
    private function createTrackableModel(TrackingEventType $eventType): ?Model
    {
        return match ($eventType) {
            TrackingEventType::MOD_CREATE,
            TrackingEventType::MOD_EDIT,
            TrackingEventType::MOD_DELETE,
            TrackingEventType::MOD_REPORT => Mod::factory()->create(),

            TrackingEventType::MOD_DOWNLOAD,
            TrackingEventType::VERSION_CREATE,
            TrackingEventType::VERSION_EDIT,
            TrackingEventType::VERSION_DELETE => ModVersion::factory()->create(),

            TrackingEventType::COMMENT_CREATE,
            TrackingEventType::COMMENT_EDIT,
            TrackingEventType::COMMENT_DELETE,
            TrackingEventType::COMMENT_LIKE,
            TrackingEventType::COMMENT_UNLIKE,
            TrackingEventType::COMMENT_REPORT => Comment::factory()->create(),

            default => null,
        };
    }

    /**
     * Generate event data using trackable model trait methods.
     *
     * @return array<string, mixed>
     */
    private function generateEventDataFromTrackable(TrackingEventType $eventType, Trackable $trackable): array
    {
        $eventData = [];

        // Use interface methods to get snapshot data and URL
        $eventData['snapshot'] = $trackable->getTrackingSnapshot();
        $eventData['url'] = $trackable->getTrackingUrl();

        // Add event-specific data for downloads
        if ($eventType === TrackingEventType::MOD_DOWNLOAD) {
            $eventData['download_size'] = $this->faker->numberBetween(1024, 104857600);
            $eventData['download_method'] = $this->faker->randomElement(['direct', 'api']);
        }

        return $eventData;
    }
}
