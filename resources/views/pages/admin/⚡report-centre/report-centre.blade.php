<x-slot:title>
    {{ __('Report Centre - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Manage and review user reports.') }}
</x-slot>

<x-slot:header>
    <h2 class="text-xl font-semibold leading-tight text-gray-100">
        {{ __('Report Centre') }}
    </h2>
</x-slot>

<div>
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div
            wire:poll.10s="$refresh"
            class="overflow-hidden bg-gray-900 shadow-xl sm:rounded-lg"
        >
            <div class="p-6">
                <div class="mb-6 border-b border-gray-700 pb-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3
                                id="reports"
                                class="text-lg font-semibold text-white"
                            >Report Centre</h3>
                            <p class="mt-1 text-sm text-gray-400">
                                Review and manage user-submitted reports about content or users that may violate
                                community
                                guidelines.
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-4">
                            <flux:switch
                                wire:model.live="filterUnresolved"
                                label="{{ __('Unresolved only') }}"
                            />
                            @if ($this->pendingReportsCount > 0)
                                <flux:badge
                                    color="yellow"
                                    size="sm"
                                >
                                    {{ $this->pendingReportsCount }}
                                    {{ $this->pendingReportsCount === 1 ? 'Pending Report' : 'Pending Reports' }}
                                </flux:badge>
                            @else
                                <flux:badge
                                    color="gray"
                                    size="sm"
                                >No Pending Reports</flux:badge>
                            @endif
                        </div>
                    </div>

                    {{-- Filters --}}
                    <div class="mt-4 flex flex-wrap items-end gap-4">
                        <div class="w-32">
                            <flux:input
                                wire:model.live.debounce.300ms="filterReportId"
                                label="{{ __('Report ID') }}"
                                placeholder="#"
                                size="sm"
                                type="number"
                                min="1"
                            />
                        </div>
                        <div class="w-48">
                            <flux:input
                                wire:model.live.debounce.300ms="filterReporterUsername"
                                label="{{ __('Reporter') }}"
                                placeholder="{{ __('Username...') }}"
                                size="sm"
                            />
                        </div>
                        @if ($filterReportId !== '' || $filterReporterUsername !== '')
                            <flux:button
                                wire:click="clearFilters"
                                variant="ghost"
                                size="sm"
                            >
                                {{ __('Clear filters') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                @if ($this->reports->count() > 0)
                    <div class="space-y-4">
                        @foreach ($this->reports as $report)
                            <div
                                class="group relative overflow-hidden rounded-xl border border-gray-700 bg-gray-800 shadow-sm transition-shadow duration-200 hover:shadow-md">
                                {{-- Status indicator bar --}}
                                <div
                                    class="bg-{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'yellow-400' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'green-400' : 'gray-400') }} absolute inset-y-0 left-0 w-1">
                                </div>

                                <div class="p-4 pl-6">
                                    {{-- Main content layout --}}
                                    <div class="flex flex-col space-y-4 lg:flex-row lg:space-x-4 lg:space-y-0">
                                        {{-- Left side: Report details --}}
                                        <div class="flex-1">
                                            {{-- Header with reporter and status --}}
                                            <div class="mb-3 flex items-start justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <flux:avatar
                                                            circle="circle"
                                                            src="{{ $report->reporter->profile_photo_url }}"
                                                            name="{{ $report->reporter->name }}"
                                                            color="auto"
                                                            color:seed="{{ $report->reporter->id }}"
                                                            size="sm"
                                                        />
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-white">
                                                            <span
                                                                class="capitalize">{{ $report->reporter->display_name ?? $report->reporter->name }}</span>
                                                            reports <span
                                                                class="lowercase text-red-400">{{ $report->reason->label() }}</span>
                                                        </p>
                                                        <p class="text-xs text-gray-400">
                                                            {{ $report->created_at->diffForHumans() }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    {{-- Report ID --}}
                                                    <flux:badge
                                                        color="zinc"
                                                        size="sm"
                                                    >
                                                        #{{ $report->id }}
                                                    </flux:badge>

                                                    {{-- Assignee badge --}}
                                                    @if ($report->assignee)
                                                        <flux:badge
                                                            color="blue"
                                                            size="sm"
                                                            icon="user"
                                                        >
                                                            {{ $report->assignee->id === auth()->id() ? 'You' : $report->assignee->name }}
                                                        </flux:badge>
                                                    @endif

                                                    <flux:badge
                                                        color="{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'yellow' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'green' : 'gray') }}"
                                                        size="sm"
                                                        icon="{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'clock' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'check-circle' : 'x-circle') }}"
                                                    >
                                                        {{ $report->status->label() }}
                                                    </flux:badge>
                                                </div>
                                            </div>

                                            {{-- Report details --}}
                                            <div class="space-y-2 lg:mb-3">

                                                @if ($report->context)
                                                    <div class="flex items-start space-x-2">
                                                        <flux:icon.chat-bubble-left-ellipsis
                                                            class="mt-0.5 size-4 flex-shrink-0 text-blue-500"
                                                        />
                                                        <div>
                                                            <span class="text-sm font-medium text-white">Reason:</span>
                                                            <p class="text-sm text-gray-400">
                                                                {{ $report->context }}</p>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if ($report->context)
                                                    <div class="flex items-start space-x-2">
                                                        <flux:icon.information-circle
                                                            class="mt-1.5 size-4 flex-shrink-0 text-amber-500"
                                                        />
                                                        <div>
                                                            <span class="text-sm font-medium text-white">Additional
                                                                Context:</span>
                                                            <p class="text-sm text-gray-400">
                                                                {{ $report->context }}</p>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Quick Actions Section - Only visible to the moderator who picked up the report --}}
                                            @if (
                                                $report->status === \App\Enums\ReportStatus::PENDING &&
                                                    $report->reportable &&
                                                    $report->assignee_id === auth()->id())
                                                <div class="mt-3 border-t border-gray-700 pt-3">
                                                    <div
                                                        class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-400">
                                                        Quick Actions
                                                    </div>
                                                    <div class="mb-3 flex flex-wrap gap-2">
                                                        @if ($report->reportable_type === 'App\Models\User')
                                                            @if ($report->reportable->isBanned())
                                                                @can('unban', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="filled"
                                                                        icon="shield-check"
                                                                        wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                    >
                                                                        Unban User
                                                                    </flux:button>
                                                                @endcan
                                                            @else
                                                                @can('ban', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="danger"
                                                                        icon="no-symbol"
                                                                        wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                    >
                                                                        Ban User
                                                                    </flux:button>
                                                                @endcan
                                                            @endif
                                                        @elseif ($report->reportable_type === 'App\Models\Mod')
                                                            @if ($report->reportable->disabled)
                                                                @can('disable', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="filled"
                                                                        icon="eye"
                                                                        wire:click="openActionModal({{ $report->id }}, 'enable_mod')"
                                                                    >
                                                                        Enable Mod
                                                                    </flux:button>
                                                                @endcan
                                                            @else
                                                                @can('disable', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="danger"
                                                                        icon="eye-slash"
                                                                        wire:click="openActionModal({{ $report->id }}, 'disable_mod')"
                                                                    >
                                                                        Disable Mod
                                                                    </flux:button>
                                                                @endcan
                                                            @endif
                                                            @if ($report->reportable->owner)
                                                                @if ($report->reportable->owner->isBanned())
                                                                    @can('unban', $report->reportable->owner)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="filled"
                                                                            icon="shield-check"
                                                                            wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                        >
                                                                            Unban Owner
                                                                        </flux:button>
                                                                    @endcan
                                                                @else
                                                                    @can('ban', $report->reportable->owner)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="danger"
                                                                            icon="no-symbol"
                                                                            wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                        >
                                                                            Ban Owner
                                                                        </flux:button>
                                                                    @endcan
                                                                @endif
                                                            @endif
                                                        @elseif ($report->reportable_type === 'App\Models\Addon')
                                                            @if ($report->reportable->disabled)
                                                                @can('disable', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="filled"
                                                                        icon="eye"
                                                                        wire:click="openActionModal({{ $report->id }}, 'enable_addon')"
                                                                    >
                                                                        Enable Addon
                                                                    </flux:button>
                                                                @endcan
                                                            @else
                                                                @can('disable', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="danger"
                                                                        icon="eye-slash"
                                                                        wire:click="openActionModal({{ $report->id }}, 'disable_addon')"
                                                                    >
                                                                        Disable Addon
                                                                    </flux:button>
                                                                @endcan
                                                            @endif
                                                            @if ($report->reportable->owner)
                                                                @if ($report->reportable->owner->isBanned())
                                                                    @can('unban', $report->reportable->owner)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="filled"
                                                                            icon="shield-check"
                                                                            wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                        >
                                                                            Unban Owner
                                                                        </flux:button>
                                                                    @endcan
                                                                @else
                                                                    @can('ban', $report->reportable->owner)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="danger"
                                                                            icon="no-symbol"
                                                                            wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                        >
                                                                            Ban Owner
                                                                        </flux:button>
                                                                    @endcan
                                                                @endif
                                                            @endif
                                                        @elseif ($report->reportable_type === 'App\Models\Comment')
                                                            @can('softDelete', $report->reportable)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="danger"
                                                                    icon="trash"
                                                                    wire:click="openActionModal({{ $report->id }}, 'delete_comment')"
                                                                >
                                                                    Soft-delete Comment
                                                                </flux:button>
                                                            @endcan
                                                            @can('restore', $report->reportable)
                                                                <flux:button
                                                                    size="xs"
                                                                    variant="filled"
                                                                    icon="arrow-path"
                                                                    wire:click="openActionModal({{ $report->id }}, 'restore_comment')"
                                                                >
                                                                    Restore Comment
                                                                </flux:button>
                                                            @endcan
                                                            @if ($report->reportable->user)
                                                                @if ($report->reportable->user->isBanned())
                                                                    @can('unban', $report->reportable->user)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="filled"
                                                                            icon="shield-check"
                                                                            wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                        >
                                                                            Unban Author
                                                                        </flux:button>
                                                                    @endcan
                                                                @else
                                                                    @can('ban', $report->reportable->user)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="danger"
                                                                            icon="no-symbol"
                                                                            wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                        >
                                                                            Ban Author
                                                                        </flux:button>
                                                                    @endcan
                                                                @endif
                                                            @endif
                                                        @elseif ($report->reportable_type === 'App\Models\ModList')
                                                            @if ($report->reportable->disabled)
                                                                @can('disable', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="filled"
                                                                        icon="eye"
                                                                        wire:click="openActionModal({{ $report->id }}, 'enable_mod_list')"
                                                                    >
                                                                        Enable List
                                                                    </flux:button>
                                                                @endcan
                                                            @else
                                                                @can('disable', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="danger"
                                                                        icon="eye-slash"
                                                                        wire:click="openActionModal({{ $report->id }}, 'disable_mod_list')"
                                                                    >
                                                                        Disable List
                                                                    </flux:button>
                                                                @endcan
                                                            @endif
                                                            @unless ($report->reportable->is_default)
                                                                @can('delete', $report->reportable)
                                                                    <flux:button
                                                                        size="xs"
                                                                        variant="danger"
                                                                        icon="trash"
                                                                        wire:click="openActionModal({{ $report->id }}, 'delete_mod_list')"
                                                                    >
                                                                        Delete List
                                                                    </flux:button>
                                                                @endcan
                                                            @endunless
                                                            @if ($report->reportable->owner)
                                                                @if ($report->reportable->owner->isBanned())
                                                                    @can('unban', $report->reportable->owner)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="filled"
                                                                            icon="shield-check"
                                                                            wire:click="openActionModal({{ $report->id }}, 'unban_user')"
                                                                        >
                                                                            Unban Owner
                                                                        </flux:button>
                                                                    @endcan
                                                                @else
                                                                    @can('ban', $report->reportable->owner)
                                                                        <flux:button
                                                                            size="xs"
                                                                            variant="danger"
                                                                            icon="no-symbol"
                                                                            wire:click="openActionModal({{ $report->id }}, 'ban_user')"
                                                                        >
                                                                            Ban Owner
                                                                        </flux:button>
                                                                    @endcan
                                                                @endif
                                                            @endif
                                                        @endif

                                                        <flux:button
                                                            size="xs"
                                                            variant="ghost"
                                                            icon="link"
                                                            wire:click="openLinkActionModal({{ $report->id }})"
                                                        >
                                                            Link Existing Action
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Actions Taken Section --}}
                                            @if ($report->actions->isNotEmpty())
                                                <div class="mt-3 border-t border-gray-700 pt-3">
                                                    <div
                                                        class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-400">
                                                        Actions Taken
                                                    </div>
                                                    <div class="mb-3 space-y-2">
                                                        @foreach ($report->actions as $action)
                                                            <div
                                                                class="group -mx-2 flex items-start gap-2 rounded px-2 py-1 text-sm hover:bg-gray-800/50">
                                                                <flux:icon
                                                                    :name="$action->trackingEvent->getEventType()?->getIcon() ?? 'check'"
                                                                    class="mt-0.5 size-4 flex-shrink-0 text-gray-400"
                                                                />
                                                                <div class="min-w-0 flex-1">
                                                                    <span class="font-medium text-white">
                                                                        {{ $action->trackingEvent->event_display_name }}
                                                                    </span>
                                                                    <span class="text-gray-400">
                                                                        by {{ $action->moderator->name }}
                                                                    </span>
                                                                    <span class="text-gray-500">
                                                                        {{ $action->created_at->diffForHumans() }}
                                                                    </span>
                                                                    @if ($action->trackingEvent->reason)
                                                                        <p class="mt-1 text-xs italic text-gray-400">
                                                                            "{{ $action->trackingEvent->reason }}"
                                                                        </p>
                                                                    @endif
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    class="flex-shrink-0 rounded p-1 opacity-0 transition-opacity hover:bg-gray-700 group-hover:opacity-100"
                                                                    wire:click="detachAction({{ $action->id }})"
                                                                    wire:confirm="Are you sure you want to detach this action from the report?"
                                                                    title="Detach action from report"
                                                                >
                                                                    <flux:icon
                                                                        name="x-mark"
                                                                        class="size-4 text-gray-400 hover:text-red-500"
                                                                    />
                                                                </button>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Action links - Desktop only --}}
                                            <div
                                                class="hidden items-center justify-between border-t border-gray-700 pt-3 lg:flex">
                                                <div class="flex items-center space-x-4">
                                                    @if ($report->reportable && method_exists($report->reportable, 'getReportableUrl'))
                                                        <a
                                                            href="{{ $report->reportable->getReportableUrl() }}"
                                                            target="_blank"
                                                            class="inline-flex items-center space-x-1 text-xs text-blue-400 transition-colors duration-150 hover:text-blue-300"
                                                        >
                                                            <flux:icon.link class="size-4" />
                                                            <span>View Content</span>
                                                        </a>
                                                    @endif
                                                </div>

                                                <div class="flex items-center space-x-4">
                                                    @if ($report->status === \App\Enums\ReportStatus::PENDING)
                                                        {{-- Pick Up / Release buttons --}}
                                                        @if ($report->assignee_id === null)
                                                            <button
                                                                wire:click="pickUp({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-blue-400 transition-colors duration-150 hover:text-blue-300"
                                                            >
                                                                <flux:icon.hand-raised class="size-4" />
                                                                <span>Pick Up</span>
                                                            </button>
                                                        @else
                                                            <button
                                                                wire:click="release({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-gray-400 transition-colors duration-150 hover:text-gray-300"
                                                            >
                                                                <flux:icon.hand-raised class="size-4" />
                                                                <span>Release</span>
                                                            </button>

                                                            {{-- Resolve/Dismiss only visible when picked up --}}
                                                            <button
                                                                wire:click="markAsResolved({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-green-400 transition-colors duration-150 hover:text-green-300"
                                                            >
                                                                <flux:icon.check class="size-4" />
                                                                <span>Resolve</span>
                                                            </button>

                                                            <button
                                                                wire:click="markAsDismissed({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-gray-400 transition-colors duration-150 hover:text-gray-300"
                                                            >
                                                                <flux:icon.x-mark class="size-4" />
                                                                <span>Dismiss</span>
                                                            </button>
                                                        @endif
                                                    @endif

                                                    @can('unresolve', $report)
                                                        @if ($report->status !== \App\Enums\ReportStatus::PENDING)
                                                            <button
                                                                wire:click="markAsUnresolved({{ $report->id }})"
                                                                wire:confirm="Are you sure you want to reopen this report?"
                                                                class="inline-flex items-center space-x-1 text-xs text-amber-400 transition-colors duration-150 hover:text-amber-300"
                                                            >
                                                                <flux:icon.arrow-path class="size-4" />
                                                                <span>Reopen</span>
                                                            </button>
                                                        @endif
                                                    @endcan

                                                    @can('delete', $report)
                                                        <button
                                                            wire:click="deleteReport({{ $report->id }})"
                                                            wire:confirm="Are you sure you want to delete this report? This action cannot be undone."
                                                            class="inline-flex items-center space-x-1 text-xs text-red-400 transition-colors duration-150 hover:text-red-300"
                                                        >
                                                            <flux:icon.trash class="size-4" />
                                                            <span>Delete</span>
                                                        </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Right side: Reported content preview --}}
                                        <div
                                            class="w-full flex-shrink-0 border-t border-gray-700 pt-4 lg:w-80 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                                            <div
                                                class="mb-2 text-xs font-medium uppercase tracking-wider text-gray-400">
                                                Reported Content
                                            </div>

                                            @if ($report->reportable)
                                                @if ($report->reportable_type === 'App\Models\Mod')
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2">
                                                            <flux:icon.cube class="size-4 text-blue-500" />
                                                            <span class="text-sm font-medium text-white">Mod</span>
                                                        </div>
                                                        <p class="text-sm font-medium text-white">
                                                            {{ $report->reportable->name }}</p>
                                                        <p class="line-clamp-3 text-xs text-gray-400">
                                                            {{ \Illuminate\Support\Str::limit($report->reportable->teaser, 120) }}
                                                        </p>
                                                    </div>
                                                @elseif($report->reportable_type === 'App\Models\User')
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2">
                                                            <flux:icon.user class="size-4 text-green-500" />
                                                            <span class="text-sm font-medium text-white">User</span>
                                                        </div>
                                                        <p class="text-sm font-medium text-white">
                                                            {{ $report->reportable->display_name ?? $report->reportable->name }}
                                                        </p>
                                                        @if ($report->reportable->about)
                                                            <p class="line-clamp-3 text-xs text-gray-400">
                                                                {{ \Illuminate\Support\Str::limit($report->reportable->about, 120) }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                @elseif($report->reportable_type === 'App\Models\Comment')
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2">
                                                            <flux:icon.chat-bubble-left
                                                                class="size-4 text-purple-500" />
                                                            <span class="text-sm font-medium text-white">Comment</span>
                                                        </div>
                                                        <p class="text-sm font-medium text-white">By
                                                            {{ $report->reportable->user ? $report->reportable->user->display_name ?? $report->reportable->user->name : 'Deleted User' }}
                                                        </p>
                                                        <div
                                                            class="prose prose-sm line-clamp-4 max-w-none text-xs text-gray-400">
                                                            {{ \Illuminate\Support\Str::limit(strip_tags($report->reportable->body), 150) }}
                                                        </div>
                                                    </div>
                                                @elseif($report->reportable_type === 'App\Models\Addon')
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2">
                                                            <flux:icon.puzzle-piece class="size-4 text-indigo-500" />
                                                            <span class="text-sm font-medium text-white">Addon</span>
                                                        </div>
                                                        <p class="text-sm font-medium text-white">
                                                            {{ $report->reportable->name }}</p>
                                                        <p class="line-clamp-3 text-xs text-gray-400">
                                                            {{ \Illuminate\Support\Str::limit($report->reportable->teaser, 120) }}
                                                        </p>
                                                    </div>
                                                @elseif($report->reportable_type === 'App\Models\ModList')
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2">
                                                            <flux:icon.list-bullet class="size-4 text-amber-500" />
                                                            <span
                                                                class="text-sm font-medium text-white">{{ __('Mod List') }}</span>
                                                        </div>
                                                        <p class="text-sm font-medium text-white">
                                                            {{ $report->reportable->getReportableTitle() }}</p>
                                                        @if ($report->reportable->getReportableExcerpt())
                                                            <p class="line-clamp-3 text-xs text-gray-400">
                                                                {{ $report->reportable->getReportableExcerpt() }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                @endif
                                            @else
                                                <div class="flex h-20 items-center justify-center text-gray-500">
                                                    <div class="text-center">
                                                        <flux:icon.exclamation-triangle class="mx-auto mb-2 size-6" />
                                                        <p class="text-xs">Content has been deleted</p>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Action links - Mobile only --}}
                                        <div class="flex flex-col space-y-3 border-t border-gray-700 pt-4 lg:hidden">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-4">
                                                    @if ($report->reportable && method_exists($report->reportable, 'getReportableUrl'))
                                                        <a
                                                            href="{{ $report->reportable->getReportableUrl() }}"
                                                            target="_blank"
                                                            class="inline-flex items-center space-x-1 text-xs text-blue-400 transition-colors duration-150 hover:text-blue-300"
                                                        >
                                                            <flux:icon.link class="size-4" />
                                                            <span>View Content</span>
                                                        </a>
                                                    @endif
                                                </div>

                                                <div class="flex items-center space-x-4">
                                                    @if ($report->status === \App\Enums\ReportStatus::PENDING)
                                                        {{-- Pick Up / Release buttons --}}
                                                        @if ($report->assignee_id === null)
                                                            <button
                                                                wire:click="pickUp({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-blue-400 transition-colors duration-150 hover:text-blue-300"
                                                            >
                                                                <flux:icon.hand-raised class="size-4" />
                                                                <span>Pick Up</span>
                                                            </button>
                                                        @else
                                                            <button
                                                                wire:click="release({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-gray-400 transition-colors duration-150 hover:text-gray-300"
                                                            >
                                                                <flux:icon.hand-raised class="size-4" />
                                                                <span>Release</span>
                                                            </button>

                                                            {{-- Resolve/Dismiss only visible when picked up --}}
                                                            <button
                                                                wire:click="markAsResolved({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-green-400 transition-colors duration-150 hover:text-green-300"
                                                            >
                                                                <flux:icon.check class="size-4" />
                                                                <span>Resolve</span>
                                                            </button>

                                                            <button
                                                                wire:click="markAsDismissed({{ $report->id }})"
                                                                class="inline-flex items-center space-x-1 text-xs text-gray-400 transition-colors duration-150 hover:text-gray-300"
                                                            >
                                                                <flux:icon.x-mark class="size-4" />
                                                                <span>Dismiss</span>
                                                            </button>
                                                        @endif
                                                    @endif

                                                    @can('unresolve', $report)
                                                        @if ($report->status !== \App\Enums\ReportStatus::PENDING)
                                                            <button
                                                                wire:click="markAsUnresolved({{ $report->id }})"
                                                                wire:confirm="Are you sure you want to reopen this report?"
                                                                class="inline-flex items-center space-x-1 text-xs text-amber-400 transition-colors duration-150 hover:text-amber-300"
                                                            >
                                                                <flux:icon.arrow-path class="size-4" />
                                                                <span>Reopen</span>
                                                            </button>
                                                        @endif
                                                    @endcan

                                                    @can('delete', $report)
                                                        <button
                                                            wire:click="deleteReport({{ $report->id }})"
                                                            wire:confirm="Are you sure you want to delete this report? This action cannot be undone."
                                                            class="inline-flex items-center space-x-1 text-xs text-red-400 transition-colors duration-150 hover:text-red-300"
                                                        >
                                                            <flux:icon.trash class="size-4" />
                                                            <span>Delete</span>
                                                        </button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->reports->count() > 10)
                        <div class="mt-6">
                            {{ $this->reports->links(data: ['scrollTo' => '#reports']) }}
                        </div>
                    @endif
                @else
                    <div class="py-8 text-center">
                        <flux:icon.document-magnifying-glass
                            size="xl"
                            class="mx-auto text-gray-400"
                        />
                        <h3 class="mt-2 text-sm font-medium text-white">No reports</h3>
                        <p class="mt-1 text-sm text-gray-400">
                            There are currently no reports to review.
                        </p>
                    </div>
                @endif
            </div>

            {{-- Action Confirmation Modal --}}
            <flux:modal
                wire:model="showActionModal"
                class="md:w-[500px] lg:w-[600px]"
            >
                <div class="space-y-0">
                    {{-- Header Section --}}
                    <div class="mb-6 border-b border-gray-700 pb-6">
                        <div class="flex items-center gap-3">
                            @if ($selectedAction === 'ban_user')
                                <flux:icon
                                    name="shield-exclamation"
                                    class="h-8 w-8 text-red-600"
                                />
                            @elseif ($selectedAction === 'unban_user')
                                <flux:icon
                                    name="shield-check"
                                    class="h-8 w-8 text-green-600"
                                />
                            @elseif ($selectedAction === 'disable_mod' || $selectedAction === 'disable_addon' || $selectedAction === 'disable_mod_list')
                                <flux:icon
                                    name="eye-slash"
                                    class="h-8 w-8 text-red-600"
                                />
                            @elseif ($selectedAction === 'enable_mod' || $selectedAction === 'enable_addon' || $selectedAction === 'enable_mod_list')
                                <flux:icon
                                    name="eye"
                                    class="h-8 w-8 text-green-600"
                                />
                            @elseif ($selectedAction === 'delete_comment' || $selectedAction === 'delete_mod_list')
                                <flux:icon
                                    name="trash"
                                    class="h-8 w-8 text-red-600"
                                />
                            @elseif ($selectedAction === 'restore_comment')
                                <flux:icon
                                    name="arrow-path"
                                    class="h-8 w-8 text-green-600"
                                />
                            @else
                                <flux:icon
                                    name="shield-exclamation"
                                    class="h-8 w-8 text-amber-600"
                                />
                            @endif
                            <div>
                                <flux:heading
                                    size="xl"
                                    class="text-gray-100"
                                >
                                    @if ($selectedAction === 'ban_user')
                                        {{ __('Ban User') }}
                                    @elseif ($selectedAction === 'unban_user')
                                        {{ __('Unban User') }}
                                    @elseif ($selectedAction === 'disable_mod')
                                        {{ __('Disable Mod') }}
                                    @elseif ($selectedAction === 'enable_mod')
                                        {{ __('Enable Mod') }}
                                    @elseif ($selectedAction === 'disable_addon')
                                        {{ __('Disable Addon') }}
                                    @elseif ($selectedAction === 'enable_addon')
                                        {{ __('Enable Addon') }}
                                    @elseif ($selectedAction === 'delete_comment')
                                        {{ __('Soft-delete Comment') }}
                                    @elseif ($selectedAction === 'restore_comment')
                                        {{ __('Restore Comment') }}
                                    @elseif ($selectedAction === 'disable_mod_list')
                                        {{ __('Disable Mod List') }}
                                    @elseif ($selectedAction === 'enable_mod_list')
                                        {{ __('Enable Mod List') }}
                                    @elseif ($selectedAction === 'delete_mod_list')
                                        {{ __('Delete Mod List') }}
                                    @else
                                        {{ __('Confirm Action') }}
                                    @endif
                                </flux:heading>
                                <flux:text class="mt-1 text-sm text-gray-400">
                                    @if ($selectedAction === 'ban_user')
                                        {{ __('Restrict user access to the platform') }}
                                    @elseif ($selectedAction === 'unban_user')
                                        {{ __('Restore user access to the platform') }}
                                    @elseif ($selectedAction === 'disable_mod')
                                        {{ __('Hide this mod from the public') }}
                                    @elseif ($selectedAction === 'enable_mod')
                                        {{ __('Make this mod visible to the public') }}
                                    @elseif ($selectedAction === 'disable_addon')
                                        {{ __('Hide this addon from the public') }}
                                    @elseif ($selectedAction === 'enable_addon')
                                        {{ __('Make this addon visible to the public') }}
                                    @elseif ($selectedAction === 'delete_comment')
                                        {{ __('Soft delete this comment (can be restored)') }}
                                    @elseif ($selectedAction === 'restore_comment')
                                        {{ __('Make this comment visible again') }}
                                    @elseif ($selectedAction === 'disable_mod_list')
                                        {{ __('Hide this list from everyone except its owner') }}
                                    @elseif ($selectedAction === 'enable_mod_list')
                                        {{ __('Make this list visible again') }}
                                    @elseif ($selectedAction === 'delete_mod_list')
                                        {{ __('Permanently delete this list') }}
                                    @else
                                        {{ __('Take action in response to this report') }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    {{-- Content Section --}}
                    <div class="space-y-6">
                        {{-- Warning Callout --}}
                        @if ($selectedAction === 'ban_user')
                            <div class="rounded-lg border border-red-800 bg-red-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="exclamation-triangle"
                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500"
                                    />
                                    <div>
                                        <flux:text class="text-sm font-medium text-red-200">
                                            {{ __('Warning') }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-red-300">
                                            {{ __('Banned users cannot access the platform when logged in, but may still access content when logged out.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @elseif ($selectedAction === 'delete_mod_list')
                            <div class="rounded-lg border border-red-800 bg-red-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="exclamation-triangle"
                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500"
                                    />
                                    <div>
                                        <flux:text class="text-sm font-medium text-red-200">
                                            {{ __('Warning') }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-red-300">
                                            {{ __('This permanently deletes the list and all of its items. This action cannot be undone.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @elseif ($selectedAction === 'delete_comment')
                            <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="information-circle"
                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500"
                                    />
                                    <div>
                                        <flux:text class="text-sm font-medium text-amber-200">
                                            {{ __('Information') }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-amber-300">
                                            {{ __('This will soft delete the comment. It can be restored by a staff member if needed.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @elseif ($selectedAction === 'restore_comment')
                            <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="information-circle"
                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                                    />
                                    <div>
                                        <flux:text class="text-sm font-medium text-green-200">
                                            {{ __('Information') }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-green-300">
                                            {{ __('This will restore the comment and make it visible to users again.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @elseif ($selectedAction === 'unban_user')
                            <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="information-circle"
                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                                    />
                                    <div>
                                        <flux:text class="text-sm font-medium text-green-200">
                                            {{ __('Information') }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-green-300">
                                            {{ __('This will restore the user\'s access to the platform. Make sure any issues have been resolved.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @elseif ($selectedAction === 'enable_mod' || $selectedAction === 'enable_addon' || $selectedAction === 'enable_mod_list')
                            <div class="rounded-lg border border-green-800 bg-green-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="information-circle"
                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-500"
                                    />
                                    <div>
                                        <flux:text class="text-sm font-medium text-green-200">
                                            {{ __('Information') }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-green-300">
                                            {{ __('This will restore public visibility. Make sure the issue has been resolved before enabling.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="rounded-lg border border-amber-800 bg-amber-900/20 p-4">
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="information-circle"
                                        class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500"
                                    />
                                    <div>
                                        <flux:text class="text-sm font-medium text-amber-200">
                                            {{ __('Information') }}
                                        </flux:text>
                                        <flux:text class="mt-1 text-sm text-amber-300">
                                            {{ __('This action will be logged and linked to the report for audit purposes.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($selectedAction === 'ban_user')
                            <div>
                                <flux:radio.group
                                    wire:model.live="banDuration"
                                    label="{{ __('Ban Duration') }}"
                                    class="text-left"
                                >
                                    <flux:radio
                                        value="1_hour"
                                        label="{{ __('1 Hour') }}"
                                    />
                                    <flux:radio
                                        value="24_hours"
                                        label="{{ __('24 Hours') }}"
                                    />
                                    <flux:radio
                                        value="7_days"
                                        label="{{ __('7 Days') }}"
                                    />
                                    <flux:radio
                                        value="30_days"
                                        label="{{ __('30 Days') }}"
                                    />
                                    <flux:radio
                                        value="permanent"
                                        label="{{ __('Permanent') }}"
                                    />
                                </flux:radio.group>
                            </div>
                        @endif

                        <div>
                            <flux:textarea
                                wire:model="actionNote"
                                label="{{ __('Reason (optional)') }}"
                                placeholder="{{ $selectedAction === 'ban_user' ? __('Please provide a reason for this ban...') : __('Explain why you\'re taking this action...') }}"
                                rows="3"
                            />
                            <p class="mt-1 text-xs text-gray-400">
                                @if ($selectedAction === 'ban_user')
                                    {{ __('This reason will be visible to the banned user.') }}
                                @else
                                    {{ __('This note will be visible to other moderators and included in the audit trail.') }}
                                @endif
                            </p>
                        </div>

                        <flux:switch
                            wire:model="resolveAfterAction"
                            label="{{ __('Resolve report after action') }}"
                            description="{{ __('Automatically mark this report as resolved after taking the action.') }}"
                        />
                    </div>

                    {{-- Footer Actions --}}
                    <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
                        <div class="flex items-center text-xs text-gray-400">
                            <flux:icon
                                name="information-circle"
                                class="mr-2 h-4 w-4 flex-shrink-0"
                            />
                            <span class="leading-tight">
                                @if ($selectedAction === 'ban_user')
                                    {{ __('This action can be reversed by unbanning the user') }}
                                @elseif ($selectedAction === 'unban_user')
                                    {{ __('This action can be reversed by banning the user again') }}
                                @elseif ($selectedAction === 'disable_mod' || $selectedAction === 'disable_addon' || $selectedAction === 'disable_mod_list')
                                    {{ __('This action can be reversed by re-enabling') }}
                                @elseif ($selectedAction === 'enable_mod' || $selectedAction === 'enable_addon' || $selectedAction === 'enable_mod_list')
                                    {{ __('This action can be reversed by disabling again') }}
                                @elseif ($selectedAction === 'delete_comment')
                                    {{ __('This action can be reversed by a staff member') }}
                                @elseif ($selectedAction === 'restore_comment')
                                    {{ __('This action can be reversed by soft-deleting again') }}
                                @elseif ($selectedAction === 'delete_mod_list')
                                    {{ __('This action is permanent and cannot be reversed') }}
                                @else
                                    {{ __('This action will be logged for audit purposes') }}
                                @endif
                            </span>
                        </div>

                        <div class="flex gap-3">
                            <flux:button
                                wire:click="$set('showActionModal', false)"
                                variant="outline"
                                size="sm"
                            >
                                {{ __('Cancel') }}
                            </flux:button>
                            <flux:button
                                wire:click="executeAction"
                                variant="{{ in_array($selectedAction, ['enable_mod', 'enable_addon', 'enable_mod_list', 'unban_user']) ? 'primary' : 'danger' }}"
                                size="sm"
                                icon="{{ $selectedAction === 'ban_user' ? 'shield-exclamation' : ($selectedAction === 'unban_user' ? 'shield-check' : (in_array($selectedAction, ['delete_comment', 'delete_mod_list']) ? 'trash' : (in_array($selectedAction, ['enable_mod', 'enable_addon', 'enable_mod_list']) ? 'eye' : 'eye-slash'))) }}"
                            >
                                @if ($selectedAction === 'ban_user')
                                    {{ __('Ban User') }}
                                @elseif ($selectedAction === 'unban_user')
                                    {{ __('Unban User') }}
                                @elseif ($selectedAction === 'disable_mod')
                                    {{ __('Disable Mod') }}
                                @elseif ($selectedAction === 'enable_mod')
                                    {{ __('Enable Mod') }}
                                @elseif ($selectedAction === 'disable_addon')
                                    {{ __('Disable Addon') }}
                                @elseif ($selectedAction === 'enable_addon')
                                    {{ __('Enable Addon') }}
                                @elseif ($selectedAction === 'delete_comment')
                                    {{ __('Soft-delete Comment') }}
                                @elseif ($selectedAction === 'disable_mod_list')
                                    {{ __('Disable Mod List') }}
                                @elseif ($selectedAction === 'enable_mod_list')
                                    {{ __('Enable Mod List') }}
                                @elseif ($selectedAction === 'delete_mod_list')
                                    {{ __('Delete Mod List') }}
                                @else
                                    {{ __('Confirm Action') }}
                                @endif
                            </flux:button>
                        </div>
                    </div>
                </div>
            </flux:modal>

            {{-- Link Existing Action Modal --}}
            <flux:modal
                wire:model="showLinkActionModal"
                class="max-w-lg"
            >
                <flux:heading size="lg">Link Existing Action</flux:heading>

                <div class="mt-4 space-y-4">
                    <p class="text-sm text-gray-400">
                        Link an existing moderation action you've taken to this report.
                    </p>

                    @if ($this->recentModerationActions->isEmpty())
                        <div class="py-4 text-center text-gray-400">
                            <flux:icon.clipboard-document-list class="mx-auto mb-2 size-8" />
                            <p class="text-sm">No recent moderation actions found.</p>
                        </div>
                    @else
                        <flux:select
                            variant="listbox"
                            wire:model="selectedTrackingEventId"
                            label="Select Action"
                        >
                            <flux:select.option value="0">Select an action...</flux:select.option>
                            @foreach ($this->recentModerationActions as $action)
                                <flux:select.option value="{{ $action->id }}">
                                    {{ $action->event_display_name }} - {{ $action->user?->name ?? 'System' }} -
                                    {{ $action->created_at->diffForHumans() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <flux:button
                        variant="ghost"
                        wire:click="$set('showLinkActionModal', false)"
                    >
                        Cancel
                    </flux:button>
                    <flux:button
                        variant="primary"
                        icon="link"
                        wire:click="linkExistingAction"
                        :disabled="$this->recentModerationActions->isEmpty()"
                    >
                        Link Action
                    </flux:button>
                </div>
            </flux:modal>
        </div>
    </div>
</div>
