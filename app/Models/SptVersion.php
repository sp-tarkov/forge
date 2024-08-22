<?php

namespace App\Models;

use App\Exceptions\InvalidVersionNumberException;
use App\Services\LatestSptVersionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class SptVersion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The relationship between an SPT version and mod version.
     */
    public function modVersions(): HasMany
    {
        return $this->hasMany(ModVersion::class);
    }

    /**
     * Determine if the version is the latest minor version.
     */
    public function isLatestMinor(): bool
    {
        $latestSptVersionService = App::make(LatestSptVersionService::class);

        $latestVersion = $latestSptVersionService->getLatestVersion();

        if (! $latestVersion) {
            return false;
        }

        try {
            [$currentMajor, $currentMinor, $currentPatch] = $this->extractVersionParts($this->version);
            [$latestMajor, $latestMinor, $latestPatch] = $this->extractVersionParts($latestVersion->version);
        } catch (InvalidVersionNumberException $e) {
            return false;
        }

        return $currentMajor == $latestMajor && $currentMinor === $latestMinor;
    }

    /**
     * Extract the version components from a full version string.
     *
     * @throws InvalidVersionNumberException
     */
    private function extractVersionParts(string $version): array
    {
        // Remove everything from the version string except the numbers and dots.
        $version = preg_replace('/[^0-9.]/', '', $version);

        // Validate that the version string is a valid semver.
        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidVersionNumberException;
        }

        $parts = explode('.', $version);

        return [
            (int) $parts[0],
            (int) $parts[1],
            (int) $parts[2],
        ];
    }
}
