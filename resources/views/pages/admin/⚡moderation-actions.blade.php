<?php

declare(strict_types=1);

use App\Enums\TrackingEventType;
use App\Models\Addon;
use App\Models\AddonVersion;
use App\Models\Comment;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\ReportAction;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::base')] #[Title('Moderation Actions - The Forge')] class extends Component {
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
        $moderationEventNames = collect(TrackingEventType::moderationActions())->map(fn(TrackingEventType $type): string => $type->value)->all();

        $query = TrackingEvent::query()
            ->whereIn('event_name', $moderationEventNames)
            ->where('is_moderation_action', true)
            ->with(['user', 'reports.reporter'])
            ->with([
                'visitable' => fn($morphTo) => $morphTo->morphWith([
                    // @pest-ignore-type
                    Mod::class => ['owner:id,name'],
                    Addon::class => ['owner:id,name'],
                    ModVersion::class => ['mod:id,name,slug,owner_id', 'mod.owner:id,name'],
                    AddonVersion::class => ['addon:id,name,slug,owner_id', 'addon.owner:id,name'],
                    Comment::class => ['user:id,name'],
                ]),
            ]);

        $this->applyFilters($query);

        return $query->latest()->paginate(25, pageName: 'actions-page');
    }

    /**
     * Get distinct moderators who have taken actions.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function moderators(): Collection
    {
        $moderationEventNames = collect(TrackingEventType::moderationActions())->map(fn(TrackingEventType $type): string => $type->value)->all();

        $moderatorIds = TrackingEvent::query()->whereIn('event_name', $moderationEventNames)->where('is_moderation_action', true)->whereNotNull('visitor_id')->distinct()->pluck('visitor_id');

        return User::query()->whereIn('id', $moderatorIds)->orderBy('name')->get();
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
        ReportAction::query()->where('tracking_event_id', $trackingEventId)->where('report_id', $reportId)->delete();

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

        if (!empty($this->search)) {
            $filters[] = sprintf("Search: '%s'", $this->search);
        }

        if (!empty($this->eventTypeFilter)) {
            $eventType = TrackingEventType::tryFrom($this->eventTypeFilter);
            if ($eventType) {
                $filters[] = sprintf('Type: %s', $eventType->label());
            }
        }

        if (!empty($this->moderatorFilter)) {
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
     * Apply filters to the query.
     *
     * @param  Builder<TrackingEvent>  $query
     */
    private function applyFilters(Builder $query): void
    {
        if (!empty($this->search)) {
            $query->where(function (Builder $q): void {
                $q->whereRaw('LOWER(JSON_EXTRACT(additional_data, "$")) LIKE ?', ['%' . mb_strtolower($this->search) . '%']);
            });
        }

        if (!empty($this->eventTypeFilter)) {
            $query->where('event_name', $this->eventTypeFilter);
        }

        if (!empty($this->moderatorFilter)) {
            $query->where('visitor_id', (int) $this->moderatorFilter);
        }

        if ($this->dateFrom) {
            $query->where('created_at', '>=', $this->dateFrom . ' 00:00:00');
        }

        if ($this->dateTo) {
            $query->where('created_at', '<=', $this->dateTo . ' 23:59:59');
        }

        if ($this->reportLinkedOnly) {
            $query->whereHas('reports');
        }
    }
};
?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
                    {{ __('Moderation Actions') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        @if ($this->getActiveFilters())
            <div class="my-6">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex-shrink-0">Filtering:</span>
                    <div class="min-w-0">
                        <flux:breadcrumbs class="inline-flex flex-wrap">
                            @foreach ($this->getActiveFilters() as $filter)
                                <flux:breadcrumbs.item separator="slash">{{ $filter }}</flux:breadcrumbs.item>
                            @endforeach
                        </flux:breadcrumbs>
                    </div>
                </div>
            </div>
        @endif

        <div class="space-y-6">
            {{-- Filters Section --}}
            <div
                id="filters-container"
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="resetFilters"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear All
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    {{-- Search Filter --}}
                    <div>
                        <flux:label
                            for="search"
                            class="text-xs"
                        >Search</flux:label>
                        <flux:input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            id="search"
                            placeholder="Search action data..."
                            size="sm"
                        />
                    </div>

                    {{-- Event Type Filter --}}
                    <div>
                        <flux:label
                            for="eventTypeFilter"
                            class="text-xs"
                        >Action Type</flux:label>
                        <flux:select
                            wire:model.live="eventTypeFilter"
                            id="eventTypeFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Types</flux:select.option>
                            @foreach ($this->moderationEventTypes as $eventType)
                                <flux:select.option value="{{ $eventType->value }}">{{ $eventType->label() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Moderator Filter --}}
                    <div>
                        <flux:label
                            for="moderatorFilter"
                            class="text-xs"
                        >Moderator</flux:label>
                        <flux:select
                            wire:model.live="moderatorFilter"
                            id="moderatorFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Moderators</flux:select.option>
                            @foreach ($this->moderators as $moderator)
                                <flux:select.option value="{{ $moderator->id }}">{{ $moderator->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Date From Filter --}}
                    <div>
                        <flux:label
                            for="dateFrom"
                            class="text-xs"
                        >Date From</flux:label>
                        <flux:input
                            type="date"
                            wire:model.live="dateFrom"
                            id="dateFrom"
                            size="sm"
                        />
                    </div>

                    {{-- Date To Filter --}}
                    <div>
                        <flux:label
                            for="dateTo"
                            class="text-xs"
                        >Date To</flux:label>
                        <flux:input
                            type="date"
                            wire:model.live="dateTo"
                            id="dateTo"
                            size="sm"
                        />
                    </div>

                    {{-- Report Linked Filter --}}
                    <div class="flex items-end pb-1">
                        <flux:checkbox
                            wire:model.live="reportLinkedOnly"
                            id="reportLinkedOnly"
                            label="Linked to reports"
                        />
                    </div>
                </div>
            </div>

            {{-- Actions Table --}}
            <div
                class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Actions ({{ number_format($this->actions->total()) }})
                    </h3>
                </div>

                {{-- Top Pagination --}}
                @if ($this->actions->hasPages())
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        {{ $this->actions->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table
                        class="w-full table-auto"
                        style="min-width: 1100px;"
                    >
                        <thead class="bg-gray-100 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Action Type
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Target
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Reason
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Moderator
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Linked Reports
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Date
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->actions as $action)
                                @php
                                    $eventType = \App\Enums\TrackingEventType::tryFrom($action->event_name);
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    {{-- Action Type --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            @php
                                                $badgeColor = match (true) {
                                                    str_contains($action->event_name, 'ban') => 'red',
                                                    str_contains($action->event_name, 'unban') => 'green',
                                                    str_contains($action->event_name, 'disable') => 'amber',
                                                    str_contains($action->event_name, 'enable') => 'green',
                                                    str_contains($action->event_name, 'delete') => 'red',
                                                    str_contains($action->event_name, 'feature') => 'sky',
                                                    str_contains($action->event_name, 'publish') => 'green',
                                                    default => 'gray',
                                                };
                                            @endphp
                                            <flux:badge
                                                color="{{ $badgeColor }}"
                                                size="sm"
                                            >
                                                {{ $eventType?->label() ?? $action->event_name }}
                                            </flux:badge>
                                        </div>
                                    </td>

                                    {{-- Target --}}
                                    <td class="px-4 py-4">
                                        @if ($action->visitable)
                                            @if ($action->visitable instanceof \App\Models\User)
                                                {{-- User Target --}}
                                                <div class="flex items-center gap-2">
                                                    <flux:avatar
                                                        circle
                                                        src="{{ $action->visitable->profile_photo_url }}"
                                                        color="auto"
                                                        color:seed="{{ $action->visitable->id }}"
                                                        size="xs"
                                                    />
                                                    <div class="flex flex-col min-w-0">
                                                        <a
                                                            href="{{ $action->visitable->profile_url }}"
                                                            class="text-sm font-medium text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300 truncate"
                                                            wire:navigate
                                                        >
                                                            {{ $action->visitable->name }}
                                                        </a>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            User ID: {{ $action->visitable->id }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\Mod)
                                                {{-- Mod Target --}}
                                                <div class="flex flex-col min-w-0">
                                                    <a
                                                        href="{{ route('mod.show', ['modId' => $action->visitable->id, 'slug' => $action->visitable->slug]) }}"
                                                        class="text-sm font-medium text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-48"
                                                        wire:navigate
                                                    >
                                                        {{ $action->visitable->name }}
                                                    </a>
                                                    @if ($action->visitable->owner)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            by
                                                            <a
                                                                href="{{ $action->visitable->owner->profile_url }}"
                                                                class="underline hover:text-gray-600 dark:hover:text-gray-300"
                                                                wire:navigate
                                                            >{{ $action->visitable->owner->name }}</a>
                                                        </span>
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\Addon)
                                                {{-- Addon Target --}}
                                                <div class="flex flex-col min-w-0">
                                                    <a
                                                        href="{{ route('addon.show', ['addonId' => $action->visitable->id, 'slug' => $action->visitable->slug]) }}"
                                                        class="text-sm font-medium text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-48"
                                                        wire:navigate
                                                    >
                                                        {{ $action->visitable->name }}
                                                    </a>
                                                    @if ($action->visitable->owner)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            by
                                                            <a
                                                                href="{{ $action->visitable->owner->profile_url }}"
                                                                class="underline hover:text-gray-600 dark:hover:text-gray-300"
                                                                wire:navigate
                                                            >{{ $action->visitable->owner->name }}</a>
                                                        </span>
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\ModVersion)
                                                {{-- Mod Version Target --}}
                                                <div class="flex flex-col min-w-0">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        v{{ $action->visitable->version }}
                                                    </span>
                                                    @if ($action->visitable->mod)
                                                        <a
                                                            href="{{ route('mod.show', ['modId' => $action->visitable->mod->id, 'slug' => $action->visitable->mod->slug]) }}"
                                                            class="text-xs text-gray-500 dark:text-gray-400 underline hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-48"
                                                            wire:navigate
                                                        >
                                                            {{ $action->visitable->mod->name }}
                                                        </a>
                                                        @if ($action->visitable->mod->owner)
                                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                                by
                                                                <a
                                                                    href="{{ $action->visitable->mod->owner->profile_url }}"
                                                                    class="underline hover:text-gray-500 dark:hover:text-gray-400"
                                                                    wire:navigate
                                                                >{{ $action->visitable->mod->owner->name }}</a>
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\AddonVersion)
                                                {{-- Addon Version Target --}}
                                                <div class="flex flex-col min-w-0">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        v{{ $action->visitable->version }}
                                                    </span>
                                                    @if ($action->visitable->addon)
                                                        <a
                                                            href="{{ route('addon.show', ['addonId' => $action->visitable->addon->id, 'slug' => $action->visitable->addon->slug]) }}"
                                                            class="text-xs text-gray-500 dark:text-gray-400 underline hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-48"
                                                            wire:navigate
                                                        >
                                                            {{ $action->visitable->addon->name }}
                                                        </a>
                                                        @if ($action->visitable->addon->owner)
                                                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                                                by
                                                                <a
                                                                    href="{{ $action->visitable->addon->owner->profile_url }}"
                                                                    class="underline hover:text-gray-500 dark:hover:text-gray-400"
                                                                    wire:navigate
                                                                >{{ $action->visitable->addon->owner->name }}</a>
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\Comment)
                                                {{-- Comment Target --}}
                                                <div class="flex flex-col min-w-0">
                                                    @if ($action->visitable->getUrl())
                                                        <a
                                                            href="{{ $action->visitable->getUrl() }}"
                                                            class="text-sm font-medium text-gray-900 dark:text-gray-100 underline hover:text-gray-600 dark:hover:text-gray-300 truncate max-w-48"
                                                            wire:navigate
                                                        >
                                                            "{{ \Illuminate\Support\Str::limit(strip_tags($action->visitable->body), 50) }}"
                                                        </a>
                                                    @else
                                                        <p
                                                            class="text-sm text-gray-700 dark:text-gray-300 truncate max-w-48">
                                                            "{{ \Illuminate\Support\Str::limit(strip_tags($action->visitable->body), 50) }}"
                                                        </p>
                                                    @endif
                                                    @if ($action->visitable->user)
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            by
                                                            <a
                                                                href="{{ $action->visitable->user->profile_url }}"
                                                                class="underline hover:text-gray-600 dark:hover:text-gray-300"
                                                                wire:navigate
                                                            >{{ $action->visitable->user->name }}</a>
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                {{-- Unknown Target Type --}}
                                                <span class="text-sm text-gray-500 dark:text-gray-400 italic">
                                                    {{ class_basename($action->visitable) }}
                                                </span>
                                            @endif
                                        @else
                                            <span
                                                class="text-sm text-gray-400 dark:text-gray-500 italic">Removed</span>
                                        @endif
                                    </td>

                                    {{-- Reason --}}
                                    <td class="px-4 py-4">
                                        @if ($action->reason)
                                            <p class="text-sm text-gray-700 dark:text-gray-300 max-w-xs">
                                                {{ \Illuminate\Support\Str::limit($action->reason, 100) }}
                                            </p>
                                        @else
                                            <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </td>

                                    {{-- Moderator --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($action->user)
                                            <div class="flex items-center gap-2">
                                                <flux:avatar
                                                    circle
                                                    src="{{ $action->user->profile_photo_url }}"
                                                    size="xs"
                                                />
                                                <div>
                                                    <a
                                                        href="{{ $action->user->profile_url }}"
                                                        class="text-sm font-medium underline hover:text-gray-600 dark:hover:text-gray-300"
                                                        wire:navigate
                                                    >
                                                        {{ $action->user->name }}
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-sm text-gray-400 dark:text-gray-500 italic">System</span>
                                        @endif
                                    </td>

                                    {{-- Linked Reports --}}
                                    <td class="px-4 py-4">
                                        @if ($action->reports->isNotEmpty())
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($action->reports->take(3) as $report)
                                                    <flux:tooltip>
                                                        <flux:badge
                                                            color="sky"
                                                            size="sm"
                                                        >
                                                            #{{ $report->id }}
                                                        </flux:badge>
                                                        <flux:tooltip.content>
                                                            <div class="text-sm">
                                                                <div>Reported by:
                                                                    {{ $report->reporter?->name ?? 'Unknown' }}</div>
                                                                <div>Reason: {{ $report->reason->label() }}</div>
                                                            </div>
                                                        </flux:tooltip.content>
                                                    </flux:tooltip>
                                                @endforeach
                                                @if ($action->reports->count() > 3)
                                                    <flux:badge
                                                        color="gray"
                                                        size="sm"
                                                    >
                                                        +{{ $action->reports->count() - 3 }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                        @endif
                                    </td>

                                    {{-- Date --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ $action->created_at->format('M j, Y') }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $action->created_at->format('g:i A') }}
                                        </div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500">
                                            {{ $action->created_at->diffForHumans() }}
                                        </div>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center justify-center">
                                            @if ($action->reports->isNotEmpty())
                                                <flux:dropdown position="bottom end">
                                                    <flux:button
                                                        icon="ellipsis-horizontal"
                                                        variant="ghost"
                                                        size="sm"
                                                    />
                                                    <flux:menu>
                                                        @foreach ($action->reports as $report)
                                                            <flux:menu.item
                                                                icon="link-slash"
                                                                wire:click="detachFromReport({{ $action->id }}, {{ $report->id }})"
                                                                wire:confirm="Are you sure you want to detach this action from Report #{{ $report->id }}?"
                                                            >
                                                                Detach from Report #{{ $report->id }}
                                                            </flux:menu.item>
                                                        @endforeach
                                                    </flux:menu>
                                                </flux:dropdown>
                                            @else
                                                <span
                                                    class="inline-flex items-center justify-center p-2 text-gray-300 dark:text-gray-600"
                                                >
                                                    <flux:icon
                                                        name="minus"
                                                        class="w-5 h-5"
                                                    />
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="7"
                                        class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                    >
                                        <flux:icon.shield-check
                                            class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600"
                                        />
                                        <p class="text-gray-500 dark:text-gray-400">No moderation actions found.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Pagination --}}
                @if ($this->actions->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $this->actions->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
