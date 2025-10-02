<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for IP geolocation lookups.
 */
#[Singleton]
class GeolocationService
{
    /**
     * The GeoIP reader instance.
     */
    private ?Reader $reader = null;

    /**
     * Initialize the GeoIP reader.
     */
    public function __construct()
    {
        $this->initializeReader();
    }

    /**
     * Get location data from an IP address.
     *
     * @return array<string, mixed>
     */
    public function getLocationFromIP(string $ip): array
    {
        // Return empty data for local/private IPs
        if ($this->isLocalIP($ip)) {
            return $this->getDefaultLocationData();
        }

        // Cache location lookups for 24 hours
        return Cache::flexible('geolocation.ip.'.md5($ip), [43200, 86400], fn (): array => $this->performLookup($ip));
    }

    /**
     * Perform the actual geolocation lookup.
     *
     * @return array<string, mixed>
     */
    private function performLookup(string $ip): array
    {
        if ($this->reader === null) {
            Log::warning('GeoIP database not available for lookup', ['ip' => $ip]);

            return $this->getDefaultLocationData();
        }

        try {
            $record = $this->reader->city($ip);

            return [
                'country_code' => $record->country->isoCode,
                'country_name' => $record->country->name,
                'region_name' => $record->mostSpecificSubdivision->name,
                'city_name' => $record->city->name,
                'latitude' => $record->location->latitude,
                'longitude' => $record->location->longitude,
                'timezone' => $record->location->timeZone,
            ];
        } catch (AddressNotFoundException) {
            Log::info('IP address not found in GeoIP database', ['ip' => $ip]);

            return $this->getDefaultLocationData();
        } catch (Exception $e) {
            Log::error('GeoIP lookup failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return $this->getDefaultLocationData();
        }
    }

    /**
     * Initialize the GeoIP reader.
     */
    private function initializeReader(): void
    {
        $databasePath = storage_path('app/geoip/GeoLite2-City.mmdb');

        if (! file_exists($databasePath)) {
            Log::warning('GeoIP database not found', ['path' => $databasePath]);

            return;
        }

        try {
            $this->reader = new Reader($databasePath);
        } catch (Exception $exception) {
            Log::error('Failed to initialize GeoIP reader', [
                'path' => $databasePath,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Check if IP is local/private.
     */
    private function isLocalIP(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Get default location data for unknown/local IPs.
     *
     * @return array<string, mixed>
     */
    private function getDefaultLocationData(): array
    {
        return [
            'country_code' => null,
            'country_name' => null,
            'region_name' => null,
            'city_name' => null,
            'latitude' => null,
            'longitude' => null,
            'timezone' => null,
        ];
    }

    /**
     * Get country flag emoji from country code.
     */
    public static function getCountryFlag(string $countryCode): string
    {
        if (strlen($countryCode) !== 2) {
            return '🏳️';
        }

        $countryCode = strtoupper($countryCode);

        // Convert country code to flag emoji using regional indicator symbols
        $flag = '';
        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(ord($countryCode[$i]) - ord('A') + 0x1F1E6);
        }

        return $flag;
    }
}
