<?php

declare(strict_types=1);

namespace App\Traits\Livewire;

use App\Models\AddonVersion;
use App\Models\ModVersion;
use App\Models\User;
use App\Services\Verification\ManualVerificationService;
use Flux\Flux;

/** @phpstan-ignore trait.unused */
trait SubmitsVerification
{
    /**
     * Build the event key suffix that identifies a version in verification status events.
     */
    public static function verificationEventKey(ModVersion|AddonVersion $version): string
    {
        $prefix = $version instanceof ModVersion ? 'mod-version-' : 'addon-version-';

        return $prefix.$version->id;
    }

    /**
     * Authorize and submit a version for manual verification, toast the outcome, and notify status components.
     */
    protected function submitVerificationFor(ModVersion|AddonVersion $version): void
    {
        $this->authorize('submitVerification', $version);

        /** @var User $user */
        $user = auth()->user();

        $submission = resolve(ManualVerificationService::class)->submit($version, $user);

        Flux::toast(
            heading: $submission->outcome->toastHeading(),
            text: $submission->outcome->toastText($submission->retryAfterSeconds),
            variant: $submission->outcome->toastVariant(),
        );

        $this->dispatch('verification-submitted.'.self::verificationEventKey($version));
    }
}
