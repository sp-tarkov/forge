<?php

declare(strict_types=1);

use App\Models\User;
use App\Traits\Livewire\ConfirmsPasswords;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Features;
use Livewire\Component;

new class extends Component
{
    use ConfirmsPasswords;

    /**
     * Indicates if two factor authentication QR code is being displayed.
     */
    public bool $showingQrCode = false;

    /**
     * Indicates if the two factor authentication confirmation input and button are being displayed.
     */
    public bool $showingConfirmation = false;

    /**
     * Indicates if two factor authentication recovery codes are being displayed.
     */
    public bool $showingRecoveryCodes = false;

    /**
     * The OTP code for confirming two factor authentication.
     */
    public ?string $code = null;

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disable): void
    {
        /** @var User $user */
        $user = Auth::user();

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm') &&
            is_null($user->two_factor_confirmed_at)) {
            $disable($user);
        }
    }

    /**
     * Enable two factor authentication for the user.
     */
    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable): void
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            $this->ensurePasswordIsConfirmed();
        }

        $enable(Auth::user());

        $this->showingQrCode = true;

        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm')) {
            $this->showingConfirmation = true;
        } else {
            $this->showingRecoveryCodes = true;
        }
    }

    /**
     * Confirm two factor authentication for the user.
     */
    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm): void
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            $this->ensurePasswordIsConfirmed();
        }

        $confirm(Auth::user(), $this->code ?? '');

        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = true;

        Flux::toast(heading: 'Two Factor Authentication Enabled', text: 'Store your recovery codes in a secure password manager.', variant: 'success');
    }

    /**
     * Display the user's recovery codes.
     */
    public function showRecoveryCodes(): void
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            $this->ensurePasswordIsConfirmed();
        }

        $this->showingRecoveryCodes = true;
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            $this->ensurePasswordIsConfirmed();
        }

        $generate(Auth::user());

        $this->showingRecoveryCodes = true;

        Flux::toast(heading: 'Recovery Codes Regenerated', text: 'Your previous recovery codes are no longer valid.', variant: 'success');
    }

    /**
     * Disable two factor authentication for the user.
     */
    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable): void
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            $this->ensurePasswordIsConfirmed();
        }

        $disable(Auth::user());

        $this->showingQrCode = false;
        $this->showingConfirmation = false;
        $this->showingRecoveryCodes = false;

        Flux::toast(heading: 'Two Factor Authentication Disabled', text: 'Two factor authentication has been turned off for your account.', variant: 'success');
    }

    /**
     * Get the current user of the application.
     */
    public function getUserProperty(): User
    {
        /** @var User */
        return Auth::user();
    }

    /**
     * Determine if two factor authentication is enabled.
     */
    public function getEnabledProperty(): bool
    {
        return ! empty($this->getUserProperty()->two_factor_secret);
    }
};
