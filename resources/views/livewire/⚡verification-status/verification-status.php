<?php

declare(strict_types=1);

use App\Enums\VerificationStatus;
use App\Facades\CachedGate;
use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\VerificationResult;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * @property-read ModVersion|AddonVersion|null $version
 * @property-read bool $canManage
 * @property-read VerificationResult|null $latestResult
 * @property-read VerificationStatus|null $displayStatus
 * @property-read bool $isEligible
 * @property-read bool $isActive
 * @property-read string $tooltip
 * @property-read string $shieldIcon
 * @property-read string $shieldVariant
 * @property-read string $shieldClasses
 */
new class extends Component
{
    /**
     * The ID of the verifiable model.
     */
    #[Locked]
    public int $verifiableId;

    /**
     * The class name of the verifiable model.
     */
    #[Locked]
    public string $verifiableType;

    /**
     * The name of the flyout modal that shows the verification details.
     */
    #[Locked]
    public string $modalName;

    /**
     * The suffix used in verification event names for this version.
     */
    #[Locked]
    public string $eventKey;

    /**
     * Initializes the component when it's first mounted.
     */
    public function mount(int $verifiableId, string $verifiableType, string $modalName): void
    {
        abort_unless(in_array($verifiableType, [ModVersion::class, AddonVersion::class], true), 404);

        $this->verifiableId = $verifiableId;
        $this->verifiableType = $verifiableType;
        $this->modalName = $modalName;
        $this->eventKey = ($verifiableType === ModVersion::class ? 'mod-version-' : 'addon-version-').$verifiableId;
    }

    /**
     * Refresh the badge after a verification has been submitted for this version.
     */
    #[On('verification-submitted.{eventKey}')]
    public function refreshStatus(): void
    {
        unset($this->latestResult, $this->displayStatus, $this->isActive);
    }

    /**
     * Get the verifiable model instance.
     */
    #[Computed]
    public function version(): ModVersion|AddonVersion|null
    {
        return match ($this->verifiableType) {
            ModVersion::class => ModVersion::query()->find($this->verifiableId),
            default => AddonVersion::query()->find($this->verifiableId),
        };
    }

    /**
     * Whether the current user can manage verification for this version and see every run state.
     */
    #[Computed]
    public function canManage(): bool
    {
        return $this->version !== null && CachedGate::allows('submitVerification', $this->version);
    }

    /**
     * Get the latest verification result for the version, regardless of status.
     */
    #[Computed]
    public function latestResult(): ?VerificationResult
    {
        return VerificationResult::query()
            ->where('verifiable_type', $this->verifiableType)
            ->where('verifiable_id', $this->verifiableId)
            ->latest('id')
            ->first();
    }

    /**
     * Get the publicly visible status: the latest run's status, falling back to the version's denormalized status.
     * Null means the version is unverified, which only renders for users who can manage verification.
     */
    #[Computed]
    public function displayStatus(): ?VerificationStatus
    {
        $version = $this->version;

        if ($version === null) {
            return null;
        }

        return $this->latestResult->status ?? $version->verification_status;
    }

    /**
     * Whether the version can be submitted for verification at all (mod versions require a modern SPT constraint).
     */
    #[Computed]
    public function isEligible(): bool
    {
        $version = $this->version;

        if ($version === null) {
            return false;
        }

        return ! $version instanceof ModVersion || $version->isEligibleForVerification();
    }

    /**
     * Whether the latest verification run is queued or running.
     */
    #[Computed]
    public function isActive(): bool
    {
        return in_array($this->latestResult?->status, [VerificationStatus::Pending, VerificationStatus::Running], true);
    }

    /**
     * Get the tooltip text for the displayed status.
     */
    #[Computed]
    public function tooltip(): string
    {
        return match ($this->displayStatus) {
            VerificationStatus::Pending => __('Verification Pending'),
            VerificationStatus::Running => __('Verification Running'),
            VerificationStatus::Passed => __('Passed Verification'),
            VerificationStatus::Failed => __('Failed Verification'),
            VerificationStatus::Error => __('Verification Error'),
            null => __('Unverified'),
        };
    }

    /**
     * Get the shield icon name for the displayed status.
     */
    #[Computed]
    public function shieldIcon(): string
    {
        return match ($this->displayStatus) {
            VerificationStatus::Pending, VerificationStatus::Running => 'shield-ellipsis',
            VerificationStatus::Passed => 'shield-check',
            VerificationStatus::Failed => 'shield-x',
            VerificationStatus::Error => 'shield-alert',
            null => 'shield-question-mark',
        };
    }

    /**
     * Get the shield icon variant for the displayed status.
     */
    #[Computed]
    public function shieldVariant(): string
    {
        return $this->displayStatus === VerificationStatus::Passed ? 'solid' : 'outline';
    }

    /**
     * Get the shield color and hover classes for the displayed status.
     */
    #[Computed]
    public function shieldClasses(): string
    {
        return match ($this->displayStatus) {
            VerificationStatus::Pending => 'text-gray-400 transition hover:text-gray-300',
            VerificationStatus::Running => 'animate-pulse text-blue-400 transition hover:text-blue-300',
            VerificationStatus::Passed => 'text-blue-400 transition hover:text-blue-300 hover:drop-shadow-[0_0_8px_rgba(96,165,250,0.9)]',
            VerificationStatus::Failed => 'text-red-500 transition hover:text-red-400 hover:drop-shadow-[0_0_8px_rgba(248,113,113,0.9)]',
            VerificationStatus::Error => 'text-amber-500 transition hover:text-amber-400',
            null => 'text-gray-500 transition hover:text-gray-400',
        };
    }
};
