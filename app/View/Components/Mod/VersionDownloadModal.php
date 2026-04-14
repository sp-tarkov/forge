<?php

declare(strict_types=1);

namespace App\View\Components\Mod;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

final class VersionDownloadModal extends Component
{
    /**
     * Create a new component instance.
     *
     * @param  Collection<int, mixed>  $dependencies
     */
    public function __construct(
        public string $name,
        public string $downloadUrl,
        public string $versionString,
        public string $versionDescriptionHtml,
        public CarbonImmutable $versionUpdatedAt,
        public ?string $sptVersionFormatted = null,
        public ?string $sptVersionColorClass = null,
        public ?string $fileSize = null,
        public Collection $dependencies = new Collection,
        public bool $isLatest = true,
    ) {
        //
    }

    /**
     * Whether this version has dependencies.
     */
    public function hasDependencies(): bool
    {
        return $this->dependencies->isNotEmpty();
    }

    /**
     * The unique modal name for the dependencies modal.
     */
    public function depsModalName(): string
    {
        return $this->name.'-deps';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.mod.version-download-modal');
    }
}
