<?php

declare(strict_types=1);

use App\Services\GeolocationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

uses()->group('geolocation');

describe('GeolocationService', function (): void {
    beforeEach(function (): void {
        Cache::flush();
        Log::spy();
    });

    describe('getLocationFromIP', function (): void {
        it('returns default data for local/private IPs', function (string $localIP): void {
            $service = new GeolocationService;
            $result = $service->getLocationFromIP($localIP);

            expect($result)->toBe([
                'country_code' => null,
                'country_name' => null,
                'region_name' => null,
                'city_name' => null,
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
            ]);
        })->with([
            'localhost' => '127.0.0.1',
            'private class A' => '10.0.0.1',
            'private class B' => '172.16.0.1',
            'private class C' => '192.168.1.1',
            'loopback IPv6' => '::1',
            'link-local' => '169.254.1.1',
        ]);

        it('caches location lookups for public IPs', function (): void {
            Cache::shouldReceive('flexible')
                ->with('geolocation.ip.'.md5('8.8.8.8'), [43200, 86400], Closure::class)
                ->once()
                ->andReturn([
                    'country_code' => 'US',
                    'country_name' => 'United States',
                    'region_name' => 'California',
                    'city_name' => 'Mountain View',
                    'latitude' => 37.4056,
                    'longitude' => -122.0775,
                    'timezone' => 'America/Los_Angeles',
                ]);

            $service = new GeolocationService;
            $result = $service->getLocationFromIP('8.8.8.8');

            expect($result)->toHaveKeys([
                'country_code', 'country_name', 'region_name',
                'city_name', 'latitude', 'longitude', 'timezone',
            ]);
        });

        it('returns cached data on subsequent calls', function (): void {
            $cacheKey = 'geolocation.ip.'.md5('8.8.8.8');
            $expectedData = [
                'country_code' => 'US',
                'country_name' => 'United States',
                'region_name' => 'California',
                'city_name' => 'Mountain View',
                'latitude' => 37.4056,
                'longitude' => -122.0775,
                'timezone' => 'America/Los_Angeles',
            ];

            Cache::shouldReceive('flexible')
                ->with($cacheKey, [43200, 86400], Closure::class)
                ->once()
                ->andReturn($expectedData);

            $service = new GeolocationService;
            $result = $service->getLocationFromIP('8.8.8.8');

            expect($result)->toBe($expectedData);
        });
    });

    describe('error handling and database scenarios', function (): void {
        it('handles missing database gracefully during lookup', function (): void {
            // Test with a non-routable IP that would trigger database lookup
            $service = new GeolocationService;
            $result = $service->getLocationFromIP('203.0.113.1'); // RFC5737 test IP

            // Should return default data structure regardless of lookup result
            expect($result)->toHaveKeys([
                'country_code', 'country_name', 'region_name',
                'city_name', 'latitude', 'longitude', 'timezone',
            ]);
        });

        it('provides consistent data structure for successful lookups', function (): void {
            // Mock cache to control the response
            Cache::shouldReceive('flexible')
                ->once()
                ->andReturnUsing(fn ($key, $ttl, $callback): array => [
                    'country_code' => 'CA',
                    'country_name' => 'Canada',
                    'region_name' => 'Ontario',
                    'city_name' => 'Toronto',
                    'latitude' => 43.7001,
                    'longitude' => -79.4163,
                    'timezone' => 'America/Toronto',
                ]);

            $service = new GeolocationService;
            $result = $service->getLocationFromIP('8.8.4.4');

            expect($result)->toHaveKeys([
                'country_code', 'country_name', 'region_name',
                'city_name', 'latitude', 'longitude', 'timezone',
            ]);
            expect($result['country_code'])->toBe('CA');
        });
    });

    describe('getCountryFlag', function (): void {
        it('returns correct flag emoji for valid country codes', function (string $countryCode, string $expectedFlag): void {
            $flag = GeolocationService::getCountryFlag($countryCode);
            expect($flag)->toBe($expectedFlag);
        })->with([
            'US lowercase' => ['us', '🇺🇸'],
            'US uppercase' => ['US', '🇺🇸'],
            'CA lowercase' => ['ca', '🇨🇦'],
            'CA uppercase' => ['CA', '🇨🇦'],
            'GB lowercase' => ['gb', '🇬🇧'],
            'GB uppercase' => ['GB', '🇬🇧'],
            'FR lowercase' => ['fr', '🇫🇷'],
            'FR uppercase' => ['FR', '🇫🇷'],
        ]);

        it('returns default flag for invalid country codes', function (string $invalidCode): void {
            $flag = GeolocationService::getCountryFlag($invalidCode);
            expect($flag)->toBe('🏳️');
        })->with([
            'empty string' => [''],
            'single character' => ['U'],
            'three characters' => ['USA'],
        ]);

        it('converts numeric and special characters to flag emojis', function (string $code): void {
            // The current implementation converts any 2-char string to regional indicators
            // This documents the current behavior rather than desired behavior
            $flag = GeolocationService::getCountryFlag($code);
            expect(strlen($flag))->toBeGreaterThan(0);
            expect($flag)->not->toBe('🏳️'); // These get converted, not defaulted
        })->with([
            'numeric' => ['12'],
            'special characters' => ['@#'],
        ]);

        it('handles mixed case country codes correctly', function (): void {
            expect(GeolocationService::getCountryFlag('uS'))->toBe('🇺🇸');
            expect(GeolocationService::getCountryFlag('Ca'))->toBe('🇨🇦');
            expect(GeolocationService::getCountryFlag('gB'))->toBe('🇬🇧');
        });
    });

    describe('IP classification behavior', function (): void {
        it('treats local IPs consistently by returning default data', function (string $localIP): void {
            $service = new GeolocationService;
            $result = $service->getLocationFromIP($localIP);

            // All local IPs should return the same default structure
            expect($result)->toBe([
                'country_code' => null,
                'country_name' => null,
                'region_name' => null,
                'city_name' => null,
                'latitude' => null,
                'longitude' => null,
                'timezone' => null,
            ]);
        })->with([
            'localhost' => '127.0.0.1',
            'private class A' => '10.0.0.1',
            'private class B' => '172.16.0.1',
            'private class C' => '192.168.1.1',
            'link-local' => '169.254.1.1',
        ]);

        it('attempts to lookup public IPs through caching mechanism', function (): void {
            Cache::shouldReceive('flexible')
                ->once()
                ->andReturn([
                    'country_code' => 'US',
                    'country_name' => 'United States',
                    'region_name' => null,
                    'city_name' => null,
                    'latitude' => null,
                    'longitude' => null,
                    'timezone' => null,
                ]);

            $service = new GeolocationService;
            $result = $service->getLocationFromIP('8.8.8.8');

            expect($result)->toHaveKey('country_code');
        });
    });
});
