<?php

declare(strict_types=1);

namespace App\View\Components\Mod;

use App\Models\ModVersion;
use Illuminate\View\Component;
use Illuminate\View\View;

final class VersionCard extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public ModVersion $version,
        public ?int $latestVersionId = null,
        public ?bool $showActions = null,
    ) {
        //
    }

    /**
     * Whether this version is the latest version of the mod.
     */
    public function isLatest(): bool
    {
        return $this->latestVersionId !== null && $this->version->id === $this->latestVersionId;
    }

    /**
     * The unique modal name for this version's download modal.
     */
    public function modalName(): string
    {
        return 'version-download-'.$this->version->id;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.mod.version-card');
    }
}
