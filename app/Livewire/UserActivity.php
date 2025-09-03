<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TrackingEventType;
use App\Models\TrackingEvent;
use App\Models\User;
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
     * Initialize the component with a user.
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
            ->where('visitor_id', $this->user->id)
            ->with(['trackable'])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        // If not authenticated or not viewing own profile and not a moderator/admin, filter private events
        if (! auth()->check() ||
            (auth()->id() !== $this->user->id && ! auth()->user()->isModOrAdmin())) {
            return $events->filter(function (TrackingEvent $event) {
                // Skip events with null event names (they're considered public)
                if (! $event->event_name) {
                    return true;
                }

                $eventType = TrackingEventType::tryFrom($event->event_name);

                return ! ($eventType && $eventType->isPrivate());
            });
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
     * Render the component view.
     */
    public function render(): View
    {
        return view('livewire.user-activity');
    }
}
