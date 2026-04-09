<?php

declare(strict_types=1);

namespace App\Contracts;

interface Geolocator
{
    /**
     * @return array<string, mixed>
     */
    public function getLocationFromIP(string $ip): array;
}
