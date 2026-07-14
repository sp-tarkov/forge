<?php

declare(strict_types=1);

namespace App\View\Components\Mod;

use App\Enums\VerificationStatus;
use App\Facades\CachedGate;
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
     * Whether this version's latest file verification passed.
     */
    public function isVerified(): bool
    {
        return $this->version->verification_status === VerificationStatus::Passed;
    }

    /**
     * Whether this version's latest file verification failed and the current user is allowed to see the failure.
     */
    public function hasVisibleFailedVerification(): bool
    {
        return $this->version->verification_status === VerificationStatus::Failed
            && CachedGate::allows('viewFailedVerification', $this->version);
    }

    /**
     * The unique modal name for this version's verification details modal.
     */
    public function verificationModalName(): string
    {
        return 'version-verification-'.$this->version->id;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View
    {
        return view('components.mod.version-card');
    }
}
