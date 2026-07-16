<?php

declare(strict_types=1);

namespace App\View\Components\Addon;

use App\Models\Addon;
use App\Models\AddonVersion;
use Illuminate\View\Component;
use Illuminate\View\View;

final class VersionCard extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public AddonVersion $version,
        public Addon $addon,
        public ?bool $showActions = null,
    ) {
        //
    }

    /**
     * The unique modal name for this version's verification details modal.
     */
    public function verificationModalName(): string
    {
        return 'addon-version-verification-'.$this->version->id;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.addon.version-card');
    }
}
