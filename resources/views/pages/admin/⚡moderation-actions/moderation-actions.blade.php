<div>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    {{ __('Moderation Actions') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        @if ($this->getActiveFilters())
            <div class="my-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <span class="flex-shrink-0 text-sm font-medium text-gray-300">Filtering:</span>
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
                class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="resetFilters"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear All
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
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
                            variant="listbox"
                            searchable
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
                            variant="listbox"
                            searchable
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
                        <flux:date-picker
                            wire:model.live="dateFrom"
                            label="Date From"
                            size="sm"
                            clearable
                        />
                    </div>

                    {{-- Date To Filter --}}
                    <div>
                        <flux:date-picker
                            wire:model.live="dateTo"
                            label="Date To"
                            size="sm"
                            clearable
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
            <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900 shadow-sm">
                <div class="border-b border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-100">
                        Actions ({{ number_format($this->actions->total()) }})
                    </h3>
                </div>

                {{-- Top Pagination --}}
                @if ($this->actions->hasPages())
                    <div class="border-b border-gray-700 bg-gray-800 px-6 py-4">
                        {{ $this->actions->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table
                        class="w-full table-auto"
                        style="min-width: 1100px;"
                    >
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    Action Type
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    Target
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    Reason
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    Moderator
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    Linked Reports
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    Date
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium tracking-wider text-gray-400">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-800">
                            @forelse($this->actions as $action)
                                <tr class="hover:bg-gray-700">
                                    {{-- Action Type --}}
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <flux:badge
                                                color="{{ $this->getActionBadgeColor($action->event_name) }}"
                                                size="sm"
                                            >
                                                {{ $this->getActionLabel($action->event_name) }}
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
                                                    <div class="flex min-w-0 flex-col">
                                                        <a
                                                            href="{{ $action->visitable->profile_url }}"
                                                            class="truncate text-sm font-medium text-gray-100 underline hover:text-gray-300"
                                                            wire:navigate
                                                        >
                                                            {{ $action->visitable->name }}
                                                        </a>
                                                        <span class="text-xs text-gray-400">
                                                            User ID: {{ $action->visitable->id }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\Mod)
                                                {{-- Mod Target --}}
                                                <div class="flex min-w-0 flex-col">
                                                    <a
                                                        href="{{ route('mod.show', ['modId' => $action->visitable->id, 'slug' => $action->visitable->slug]) }}"
                                                        class="max-w-48 truncate text-sm font-medium text-gray-100 underline hover:text-gray-300"
                                                        wire:navigate
                                                    >
                                                        {{ $action->visitable->name }}
                                                    </a>
                                                    @if ($action->visitable->owner)
                                                        <span class="text-xs text-gray-400">
                                                            by
                                                            <a
                                                                href="{{ $action->visitable->owner->profile_url }}"
                                                                class="underline hover:text-gray-300"
                                                                wire:navigate
                                                            >{{ $action->visitable->owner->name }}</a>
                                                        </span>
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\Addon)
                                                {{-- Addon Target --}}
                                                <div class="flex min-w-0 flex-col">
                                                    <a
                                                        href="{{ route('addon.show', ['addonId' => $action->visitable->id, 'slug' => $action->visitable->slug]) }}"
                                                        class="max-w-48 truncate text-sm font-medium text-gray-100 underline hover:text-gray-300"
                                                        wire:navigate
                                                    >
                                                        {{ $action->visitable->name }}
                                                    </a>
                                                    @if ($action->visitable->owner)
                                                        <span class="text-xs text-gray-400">
                                                            by
                                                            <a
                                                                href="{{ $action->visitable->owner->profile_url }}"
                                                                class="underline hover:text-gray-300"
                                                                wire:navigate
                                                            >{{ $action->visitable->owner->name }}</a>
                                                        </span>
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\ModVersion)
                                                {{-- Mod Version Target --}}
                                                <div class="flex min-w-0 flex-col">
                                                    <span class="text-sm font-medium text-gray-100">
                                                        v{{ $action->visitable->version }}
                                                    </span>
                                                    @if ($action->visitable->mod)
                                                        <a
                                                            href="{{ route('mod.show', ['modId' => $action->visitable->mod->id, 'slug' => $action->visitable->mod->slug]) }}"
                                                            class="max-w-48 truncate text-xs text-gray-400 underline hover:text-gray-300"
                                                            wire:navigate
                                                        >
                                                            {{ $action->visitable->mod->name }}
                                                        </a>
                                                        @if ($action->visitable->mod->owner)
                                                            <span class="text-xs text-gray-500">
                                                                by
                                                                <a
                                                                    href="{{ $action->visitable->mod->owner->profile_url }}"
                                                                    class="underline hover:text-gray-400"
                                                                    wire:navigate
                                                                >{{ $action->visitable->mod->owner->name }}</a>
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\AddonVersion)
                                                {{-- Addon Version Target --}}
                                                <div class="flex min-w-0 flex-col">
                                                    <span class="text-sm font-medium text-gray-100">
                                                        v{{ $action->visitable->version }}
                                                    </span>
                                                    @if ($action->visitable->addon)
                                                        <a
                                                            href="{{ route('addon.show', ['addonId' => $action->visitable->addon->id, 'slug' => $action->visitable->addon->slug]) }}"
                                                            class="max-w-48 truncate text-xs text-gray-400 underline hover:text-gray-300"
                                                            wire:navigate
                                                        >
                                                            {{ $action->visitable->addon->name }}
                                                        </a>
                                                        @if ($action->visitable->addon->owner)
                                                            <span class="text-xs text-gray-500">
                                                                by
                                                                <a
                                                                    href="{{ $action->visitable->addon->owner->profile_url }}"
                                                                    class="underline hover:text-gray-400"
                                                                    wire:navigate
                                                                >{{ $action->visitable->addon->owner->name }}</a>
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            @elseif ($action->visitable instanceof \App\Models\Comment)
                                                {{-- Comment Target --}}
                                                <div class="flex min-w-0 flex-col">
                                                    @if ($action->visitable->getUrl())
                                                        <a
                                                            href="{{ $action->visitable->getUrl() }}"
                                                            class="max-w-48 truncate text-sm font-medium text-gray-100 underline hover:text-gray-300"
                                                            wire:navigate
                                                        >
                                                            "{{ \Illuminate\Support\Str::limit(strip_tags($action->visitable->body), 50) }}"
                                                        </a>
                                                    @else
                                                        <p class="max-w-48 truncate text-sm text-gray-300">
                                                            "{{ \Illuminate\Support\Str::limit(strip_tags($action->visitable->body), 50) }}"
                                                        </p>
                                                    @endif
                                                    @if ($action->visitable->user)
                                                        <span class="text-xs text-gray-400">
                                                            by
                                                            <a
                                                                href="{{ $action->visitable->user->profile_url }}"
                                                                class="underline hover:text-gray-300"
                                                                wire:navigate
                                                            >{{ $action->visitable->user->name }}</a>
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                {{-- Unknown Target Type --}}
                                                <span class="text-sm italic text-gray-400">
                                                    {{ class_basename($action->visitable) }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-sm italic text-gray-500">Removed</span>
                                        @endif
                                    </td>

                                    {{-- Reason --}}
                                    <td class="px-4 py-4">
                                        @if ($action->reason)
                                            <p class="max-w-xs text-sm text-gray-300">
                                                {{ \Illuminate\Support\Str::limit($action->reason, 100) }}
                                            </p>
                                        @else
                                            <span class="text-sm text-gray-500">-</span>
                                        @endif
                                    </td>

                                    {{-- Moderator --}}
                                    <td class="whitespace-nowrap px-4 py-4">
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
                                                        class="text-sm font-medium underline hover:text-gray-300"
                                                        wire:navigate
                                                    >
                                                        {{ $action->user->name }}
                                                    </a>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-sm italic text-gray-500">System</span>
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
                                            <span class="text-sm text-gray-500">-</span>
                                        @endif
                                    </td>

                                    {{-- Date --}}
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="text-sm text-gray-100">
                                            {{ $action->created_at->format('M j, Y') }}
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ $action->created_at->format('g:i A') }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $action->created_at->diffForHumans() }}
                                        </div>
                                    </td>

                                    {{-- Actions --}}
                                    <td class="whitespace-nowrap px-4 py-4">
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
                                                    class="inline-flex items-center justify-center p-2 text-gray-600">
                                                    <flux:icon
                                                        name="minus"
                                                        class="h-5 w-5"
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
                                        class="px-6 py-12 text-center text-gray-400"
                                    >
                                        <flux:icon.shield-check class="mx-auto mb-4 h-12 w-12 text-gray-600" />
                                        <p class="text-gray-400">No moderation actions found.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Pagination --}}
                @if ($this->actions->hasPages())
                    <div class="border-t border-gray-700 px-6 py-4">
                        {{ $this->actions->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
