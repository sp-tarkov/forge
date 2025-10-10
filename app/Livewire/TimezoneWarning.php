<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TimezoneWarning extends Component
{
    public bool $showModal = false;

    public string $detectedTimezone = '';

    public string $fallbackTimezone = '';

    public bool $useJsDetection = true;

    public function openModal(): void
    {
        $this->loadFallbackTimezone();
        $this->showModal = true;
    }

    public function saveTimezone(): void
    {
        $timezoneToSave = $this->useJsDetection
            ? $this->detectedTimezone
            : $this->fallbackTimezone;

        if ($timezoneToSave) {
            Auth::user()->update(['timezone' => $timezoneToSave]);
            $this->showModal = false;

            // Flash success message
            flash()->success(__('Timezone updated successfully to :timezone', ['timezone' => $timezoneToSave]));

            $this->dispatch('$refresh');
        }
    }

    public function redirectToProfile(): void
    {
        $this->showModal = false;
        $this->redirect(route('profile.show'));
    }

    public function setDetectedTimezone(string $timezone): void
    {
        $this->detectedTimezone = $timezone;
    }

    #[Computed]
    public function shouldShowWarning(): bool
    {
        return Auth::check() && Auth::user()->timezone === null;
    }

    public function render(): View
    {
        return view('livewire.timezone-warning');
    }

    private function loadFallbackTimezone(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $latestEvent = TrackingEvent::query()
            ->where('visitor_type', User::class)
            ->where('visitor_id', $user->id)
            ->whereNotNull('timezone')
            ->latest('created_at')
            ->first();

        $this->fallbackTimezone = $latestEvent?->timezone ?: 'UTC';
    }
}
