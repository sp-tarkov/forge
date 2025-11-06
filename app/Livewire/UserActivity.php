<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TrackingEventType;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UserActivity extends Component
{
    /**
     * The user whose activity is being displayed.
     */
    public User $user;

    /**
     * Livewire lifecycle method: Initialize the component with a user.
     *
     * This magic method is automatically called when the component is first mounted.
     * It receives the User model passed from the parent view.
     */
    public function mount(User $user): void
    {
        $this->user = $user;
    }

    /**
     * Get the user's recent activity events.
     *
     * @return Collection<int, TrackingEvent>
     */
    #[Computed]
    public function recentActivity(): Collection
    {
        $events = TrackingEvent::query()
            // Get all events where the user is the visitor (simplified since logout now has visitor_id)
            ->where('visitor_id', $this->user->id)
            ->with('visitable')
            ->latest()
            ->limit(15)
            ->get();

        // Eagerly load the mod relationship without global scopes for ModVersion visitables only
        $events->each(function (TrackingEvent $event): void {
            if ($event->visitable instanceof ModVersion && ! $event->visitable->relationLoaded('mod')) {
                $event->visitable->loadMissing(['mod' => fn (Builder $query): Builder => $query->withoutGlobalScopes()]);
            }
        });

        // If not authenticated or not viewing own profile and not a moderator/admin, filter private events
        if (! auth()->check() ||
            (auth()->id() !== $this->user->id && ! auth()->user()->isModOrAdmin())) {
            return $events->reject(fn (TrackingEvent $event): bool => $this->shouldEventBePrivate($event));
        }

        return $events;
    }

    /**
     * Get the TrackingEventType enum for the given event.
     */
    public function getEventType(TrackingEvent $event): ?TrackingEventType
    {
        return $event->event_name ? TrackingEventType::tryFrom($event->event_name) : null;
    }

    /**
     * Get the icon for the given event.
     */
    public function getEventIcon(TrackingEvent $event): string
    {
        return $this->getEventType($event)?->getIcon() ?? 'document-text';
    }

    /**
     * Get the color for the given event.
     */
    public function getEventColor(TrackingEvent $event): string
    {
        return $this->getEventType($event)?->getColor() ?? 'gray';
    }

    /**
     * Determine if the event has context to show in a container.
     */
    public function hasContext(TrackingEvent $event): bool
    {
        $eventType = $this->getEventType($event);
        $shouldShowContext = ! $eventType || $eventType->shouldShowContext();

        return $shouldShowContext && ! empty($event->event_context);
    }

    /**
     * Determine if an event is private and should show the lock icon.
     */
    public function isEventPrivate(TrackingEvent $event): bool
    {
        $eventType = $this->getEventType($event);

        return $eventType && $eventType->isPrivate();
    }

    /**
     * Livewire lifecycle method: Render the component view.
     *
     * This magic method is automatically called by Livewire to generate the component's
     * HTML output. It's called on initial load and after any property updates.
     */
    public function render(): View
    {
        return view('livewire.user-activity');
    }

    /**
     * Determine if an event should be treated as private based on event type or related models.
     * This is used to filter events for non-owners/non-admins.
     */
    private function shouldEventBePrivate(TrackingEvent $event): bool
    {
        // Check if the event type itself is private
        if (! $event->event_name) {
            return false;
        }

        $eventType = TrackingEventType::tryFrom($event->event_name);
        if ($eventType && $eventType->isPrivate()) {
            return true;
        }

        // For mod-related events, check if the mod is unpublished
        if ($event->visitable instanceof ModVersion) {
            $mod = $event->visitable->mod;
            if (is_null($mod->published_at) || $mod->published_at > now()) {
                return true;
            }
        }

        if ($event->visitable instanceof Mod) {
            if (is_null($event->visitable->published_at) || $event->visitable->published_at > now()) {
                return true;
            }
        }

        return false;
    }
}
