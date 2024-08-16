<?php

namespace App\Services;

use App\Models\SptVersion;

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
