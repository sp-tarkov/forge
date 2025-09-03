<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\GeolocationService;
use App\Services\TrackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

describe('TrackService', function (): void {
    beforeEach(function (): void {
        $this->geolocationService = $this->mock(GeolocationService::class);
        $this->trackService = new TrackService($this->geolocationService);

        // Disable defer to make deferred callbacks execute synchronously in tests
        $this->withoutDefer();
    });

    describe('event tracking', function (): void {
        it('tracks authentication events without trackable model', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->with('127.0.0.1')
                ->once()
                ->andReturn([
                    'country_code' => 'US',
                    'country_name' => 'United States',
                    'region_name' => 'California',
                    'city_name' => 'San Francisco',
                    'latitude' => 37.7749,
                    'longitude' => -122.4194,
                    'timezone' => 'America/Los_Angeles',
                ]);

            $this->trackService->event(TrackingEventType::LOGIN);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'login',
                'visitor_id' => $user->id,
                'visitor_type' => User::class,
                'visitable_type' => null,
                'visitable_id' => null,
                'country_code' => 'US',
                'country_name' => 'United States',
                'region_name' => 'California',
                'city_name' => 'San Francisco',
            ]);
        });

        it('tracks events with trackable models', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            $this->trackService->event(TrackingEventType::MOD_CREATE, $mod);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'mod_create',
                'visitor_id' => $user->id,
                'visitable_type' => Mod::class,
                'visitable_id' => $mod->id,
            ]);
        });

        it('tracks events for anonymous users', function (): void {
            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            $this->trackService->event(TrackingEventType::LOGIN);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'login',
                'visitor_id' => null,
                'visitor_type' => null,
            ]);
        });

        it('tracks events with additional data', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            $additionalData = [
                'custom_field' => 'custom_value',
                'count' => 42,
            ];

            $this->trackService->event(TrackingEventType::REGISTER, null, $additionalData);

            $event = TrackingEvent::query()->where('event_name', 'register')->first();
            expect($event->event_data)->toMatchArray($additionalData);
        });

        it('tracks comment events with trackable model', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            $this->trackService->event(TrackingEventType::COMMENT_LIKE, $comment);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'comment_like',
                'visitor_id' => $user->id,
                'visitable_type' => Comment::class,
                'visitable_id' => $comment->id,
            ]);
        });
    });

    describe('request data capture', function (): void {
        it('captures request information correctly', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            // Create a test request with specific headers
            $request = Request::create('/test-path', 'GET', [], [], [], [
                'HTTP_REFERER' => 'https://example.com/referrer',
                'HTTP_USER_AGENT' => 'Test Browser 1.0',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
            ]);

            $this->app->instance('request', $request);

            $this->trackService->event(TrackingEventType::LOGIN);

            $event = TrackingEvent::query()->where('event_name', 'login')->first();

            expect($event->url)->toBe('/test-path');
            expect($event->referer)->toBe('https://example.com/referrer');
            expect($event->useragent)->toBe('Test Browser 1.0');
            expect($event->languages)->toBeArray();
        });

        it('handles missing geolocation gracefully', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([
                    'country_code' => null,
                    'country_name' => null,
                    'region_name' => null,
                    'city_name' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'timezone' => null,
                ]);

            $this->trackService->event(TrackingEventType::LOGIN);

            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'login',
                'country_code' => null,
                'country_name' => null,
                'region_name' => null,
                'city_name' => null,
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
            ]);
        });
    });

    describe('error handling', function (): void {
        it('logs errors when tracking fails but does not throw exceptions', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            // Mock GeolocationService to throw an exception
            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andThrow(new Exception('Geolocation service failed'));

            // This should not throw an exception even if geolocation fails
            expect(fn () => $this->trackService->event(TrackingEventType::LOGIN))->not->toThrow(Exception::class);

            // The event should not be created due to the error
            $this->assertDatabaseMissing('tracking_events', [
                'event_name' => 'login',
            ]);
        });

        it('handles visitor data extraction failures gracefully', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            // Create a request with invalid user agent to potentially cause visitor() to fail
            $request = Request::create('/test', 'GET', [], [], [], [
                'HTTP_USER_AGENT' => '',
            ]);
            $this->app->instance('request', $request);

            $this->trackService->event(TrackingEventType::LOGIN);

            // Should still create the event even if visitor data fails
            $this->assertDatabaseHas('tracking_events', [
                'event_name' => 'login',
            ]);
        });
    });

    describe('trackable model integration', function (): void {
        it('extracts tracking snapshot from trackable models', function (): void {
            $user = User::factory()->create();
            $comment = Comment::factory()->create([
                'body' => 'This is a test comment for tracking',
            ]);
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            $this->trackService->event(TrackingEventType::COMMENT_CREATE, $comment);

            $event = TrackingEvent::query()->where('event_name', 'comment_create')->first();

            expect($event->event_data)->toHaveKey('snapshot');
            expect($event->event_data)->toHaveKey('url');
            expect($event->event_data['snapshot'])->toBeArray();
        });

        it('works with trackable models and extracts snapshots', function (): void {
            $user = User::factory()->create();
            $mod = Mod::factory()->create();
            $this->actingAs($user);

            $this->geolocationService
                ->shouldReceive('getLocationFromIP')
                ->once()
                ->andReturn([]);

            $this->trackService->event(TrackingEventType::MOD_CREATE, $mod);

            $event = TrackingEvent::query()->where('event_name', 'mod_create')->first();

            expect($event->visitable_type)->toBe(Mod::class);
            expect($event->visitable_id)->toBe($mod->id);
            // Should have snapshot data since Mod implements Trackable
            expect($event->event_data)->toHaveKey('snapshot');
            expect($event->event_data)->toHaveKey('url');
        });
    });

    describe('initialization', function (): void {
        it('can be instantiated with a geolocation service', function (): void {
            expect($this->trackService)->toBeInstanceOf(TrackService::class);
        });

        it('integrates with geolocation service', function (): void {
            // The service should have the geolocation service as a dependency
            $reflection = new ReflectionClass($this->trackService);
            $property = $reflection->getProperty('geolocationService');

            expect($property->getValue($this->trackService))->toBe($this->geolocationService);
        });
    });

    describe('method signature validation', function (): void {
        it('has correct method signature for event method', function (): void {
            $reflection = new ReflectionClass(TrackService::class);
            $method = $reflection->getMethod('event');

            expect($method->isPublic())->toBeTrue();
            expect($method->getNumberOfParameters())->toBe(3);

            $parameters = $method->getParameters();
            expect($parameters[0]->getName())->toBe('eventType');
            expect($parameters[1]->getName())->toBe('trackable');
            expect($parameters[2]->getName())->toBe('additionalData');

            // Check parameter types
            expect($parameters[0]->getType()?->getName())->toBe(TrackingEventType::class);
            expect($parameters[1]->allowsNull())->toBeTrue();
            expect($parameters[2]->hasType())->toBeTrue();
            expect($parameters[2]->getType()?->getName())->toBe('array');
        });
    });
});
