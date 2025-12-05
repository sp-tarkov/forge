<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\TrackingEventType;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\ReportAction;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ModerationActions extends Component
{
    use WithPagination;

    /** Search term for action content. */
    public string $search = '';

    /** Filter by event type. */
    public string $eventTypeFilter = '';

    /** Filter by moderator ID. */
    public string $moderatorFilter = '';

    /** Filter by date from. */
    public ?string $dateFrom = null;

    /** Filter by date to. */
    public ?string $dateTo = null;

    /** Show only actions linked to reports. */
    public bool $reportLinkedOnly = false;

    /**
     * Initialize the component.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->isModOrAdmin(), 403, 'Access denied. Moderator privileges required.');
    }

    /**
     * Get paginated moderation actions.
     *
     * @return LengthAwarePaginator<int, TrackingEvent>
     */
    #[Computed]
    public function actions(): LengthAwarePaginator
    {
        $moderationEventNames = collect(TrackingEventType::moderationActions())
            ->map(fn (TrackingEventType $type): string => $type->value)
            ->all();

        $query = TrackingEvent::query()
            ->whereIn('event_name', $moderationEventNames)
            ->where('is_moderation_action', true)
            ->with(['user', 'reports.reporter'])
            ->with(['visitable' => fn ($morphTo) => $morphTo->morphWith([ // @pest-ignore-type
                Mod::class => ['owner:id,name'],
                Addon::class => ['owner:id,name'],
                ModVersion::class => ['mod:id,name,slug,owner_id', 'mod.owner:id,name'],
                AddonVersion::class => ['addon:id,name,slug,owner_id', 'addon.owner:id,name'],
                Comment::class => ['user:id,name'],
            ])]);

        $this->applyFilters($query);

        return $query
            ->latest()
            ->paginate(25, pageName: 'actions-page');
    }

    /**
     * Get distinct moderators who have taken actions.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function moderators(): Collection
    {
        $moderationEventNames = collect(TrackingEventType::moderationActions())
            ->map(fn (TrackingEventType $type): string => $type->value)
            ->all();

        $moderatorIds = TrackingEvent::query()
            ->whereIn('event_name', $moderationEventNames)
            ->where('is_moderation_action', true)
            ->whereNotNull('visitor_id')
            ->distinct()
            ->pluck('visitor_id');

        return User::query()
            ->whereIn('id', $moderatorIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get available moderation event types.
     *
     * @return array<int, TrackingEventType>
     */
    #[Computed]
    public function moderationEventTypes(): array
    {
        return TrackingEventType::moderationActions();
    }

    /**
     * Reset all filters.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->eventTypeFilter = '';
        $this->moderatorFilter = '';
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->reportLinkedOnly = false;
        $this->resetPage(pageName: 'actions-page');
    }

    /**
     * Reset pagination when filters change.
     */
    public function updatedSearch(): void
    {
        $this->resetPage(pageName: 'actions-page');
    }

    public function updatedEventTypeFilter(): void
    {
        $this->resetPage(pageName: 'actions-page');
    }

    public function updatedModeratorFilter(): void
    {
        $this->resetPage(pageName: 'actions-page');
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage(pageName: 'actions-page');
    }

    public function updatedDateTo(): void
    {
        $this->resetPage(pageName: 'actions-page');
    }

    public function updatedReportLinkedOnly(): void
    {
        $this->resetPage(pageName: 'actions-page');
    }

    /**
     * Detach a tracking event from a specific report.
     */
    public function detachFromReport(int $trackingEventId, int $reportId): void
    {
        ReportAction::query()
            ->where('tracking_event_id', $trackingEventId)
            ->where('report_id', $reportId)
            ->delete();

        flash()->success('Action detached from report.');
        $this->dispatch('$refresh');
    }

    /**
     * Get the active filters for display.
     *
     * @return array<int, string>
     */
    public function getActiveFilters(): array
    {
        $filters = [];

        if (! empty($this->search)) {
            $filters[] = sprintf("Search: '%s'", $this->search);
        }

        if (! empty($this->eventTypeFilter)) {
            $eventType = TrackingEventType::tryFrom($this->eventTypeFilter);
            if ($eventType) {
                $filters[] = sprintf('Type: %s', $eventType->label());
            }
        }

        if (! empty($this->moderatorFilter)) {
            $moderator = $this->moderators()->firstWhere('id', (int) $this->moderatorFilter);
            if ($moderator) {
                $filters[] = sprintf('Moderator: %s', $moderator->name);
            }
        }

        if ($this->dateFrom && $this->dateTo) {
            $filters[] = sprintf('Date: %s - %s', $this->dateFrom, $this->dateTo);
        } elseif ($this->dateFrom) {
            $filters[] = sprintf('From: %s', $this->dateFrom);
        } elseif ($this->dateTo) {
            $filters[] = sprintf('Until: %s', $this->dateTo);
        }

        if ($this->reportLinkedOnly) {
            $filters[] = 'Linked to reports only';
        }

        return $filters;
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.admin.moderation-actions')->layout('components.layouts.base', [
            'title' => 'Moderation Actions - The Forge',
            'description' => 'View all moderation actions taken by staff members.',
        ]);
    }

    /**
     * Apply filters to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyFilters(Builder $query): void
    {
        if (! empty($this->search)) {
            $query->where(function (Builder $q): void {
                $q->whereRaw('LOWER(JSON_EXTRACT(additional_data, "$")) LIKE ?', ['%'.mb_strtolower($this->search).'%']);
            });
        }

        if (! empty($this->eventTypeFilter)) {
            $query->where('event_name', $this->eventTypeFilter);
        }

        if (! empty($this->moderatorFilter)) {
            $query->where('visitor_id', (int) $this->moderatorFilter);
        }

        if ($this->dateFrom) {
            $query->where('created_at', '>=', $this->dateFrom.' 00:00:00');
        }

        if ($this->dateTo) {
            $query->where('created_at', '<=', $this->dateTo.' 23:59:59');
        }

        if ($this->reportLinkedOnly) {
            $query->whereHas('reports');
        }
    }
}
