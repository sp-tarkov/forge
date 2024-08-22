<?php

namespace App\Services;

use App\Models\SptVersion;

/**
 * This class is responsible for fetching the latest SPT version. It's registered as a singleton in the service
 * container so that the latest version is only fetched once per request.
 */
class LatestSptVersionService
{
    protected ?SptVersion $version = null;

    public function getLatestVersion(): ?SptVersion
    {
        if ($this->version === null) {
            $this->version = SptVersion::select('version')->orderByDesc('version')->first();
        }

        return $this->version;
    }
}
