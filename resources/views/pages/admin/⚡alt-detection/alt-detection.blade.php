<div>
    <style>
        .alt-skeleton-bar {
            position: relative;
            overflow: hidden;
        }

        .alt-skeleton-bar::after {
            content: "";
            position: absolute;
            inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.45), transparent);
            animation: alt-skeleton-shimmer 1.6s infinite;
        }

        @keyframes alt-skeleton-shimmer {
            100% {
                transform: translateX(100%);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .alt-skeleton-bar::after {
                animation: none;
            }
        }
    </style>

    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    {{ __('Alt Detection') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 py-6 lg:px-8">
        <div class="space-y-6">
            <flux:callout
                variant="secondary"
                icon="finger-print"
            >
                <flux:callout.heading>Correlate accounts that may belong to the same person</flux:callout.heading>
                <flux:callout.text>
                    Pick a suspect to surface other accounts linked by shared IP addresses, email domain, activity
                    timing, and device fingerprint. Since-deleted accounts are recovered from their leftover activity
                    and
                    flagged. Candidates are ranked by a confidence score. Treat the result as a lead, not proof: IP
                    addresses can be shared through VPNs, mobile carriers, or a single household, so always review the
                    underlying evidence before taking action.
                </flux:callout.text>
            </flux:callout>

            @if ($this->suspectId === null)
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        label="Find a user to investigate"
                        placeholder="Name, email, or ID..."
                        icon="magnifying-glass"
                    />

                    @if (mb_strlen(trim($search)) >= 2)
                        <div class="mt-4 divide-y divide-gray-700">
                            @forelse ($this->searchResults as $user)
                                <button
                                    type="button"
                                    wire:click="selectSuspect({{ $user->id }})"
                                    class="-mx-2 flex w-full items-center gap-3 rounded-md px-2 py-3 text-left hover:bg-gray-800"
                                >
                                    <flux:avatar
                                        circle="circle"
                                        src="{{ $user->profile_photo_url }}"
                                        color="auto"
                                        color:seed="{{ $user->id }}"
                                        size="sm"
                                    />
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-gray-100">
                                            {{ $user->name }}</div>
                                        <div class="truncate text-xs text-gray-400">
                                            {{ $user->email }} · ID {{ $user->id }}</div>
                                    </div>
                                </button>
                            @empty
                                <p class="py-3 text-sm text-gray-400">No users found.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <flux:avatar
                                circle="circle"
                                src="{{ $this->suspect?->profile_photo_url }}"
                                color="auto"
                                color:seed="{{ $this->suspectId }}"
                            />
                            <div class="min-w-0">
                                <div class="text-xs uppercase tracking-wide text-gray-500">Suspect
                                </div>
                                <a
                                    href="{{ $this->suspect?->profile_url }}"
                                    target="_blank"
                                    class="text-base font-semibold text-gray-100 underline hover:text-gray-300"
                                >{{ $this->suspect?->name }}</a>
                                <div class="text-xs text-gray-400">{{ $this->suspect?->email }} · ID
                                    {{ $this->suspectId }}</div>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($this->run && !$this->run->inProgress())
                                <flux:button
                                    wire:click="reRun"
                                    variant="outline"
                                    size="sm"
                                    icon="arrow-path"
                                >
                                    Re-run
                                </flux:button>
                            @endif
                            <flux:button
                                wire:click="clearSuspect"
                                variant="outline"
                                size="sm"
                                icon="arrow-uturn-left"
                            >
                                Investigate another
                            </flux:button>
                        </div>
                    </div>
                </div>

                @if ($this->run?->isFailed())
                    <div class="rounded-lg border border-gray-700 bg-gray-900 p-8 text-center shadow-sm">
                        <flux:icon.exclamation-triangle class="mx-auto mb-3 h-10 w-10 text-red-400" />
                        <p class="text-sm font-medium text-gray-100">The investigation could not be
                            completed.</p>
                        <p class="mt-1 text-xs text-gray-400">{{ $this->run->error }}</p>
                        <flux:button
                            wire:click="reRun"
                            variant="primary"
                            size="sm"
                            icon="arrow-path"
                            class="mt-4"
                        >
                            Try again
                        </flux:button>
                    </div>
                @elseif ($this->run?->isCompleted() && $this->result)
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-400">
                            <span>{{ number_format($this->result->candidateCount()) }} candidate(s)</span>
                            <span>{{ number_format($this->result->suspectIpCount) }} IP(s)
                                analyzed</span>
                            <span>{{ number_format($this->result->excludedNoisyIps) }} shared/noisy IP(s)
                                excluded</span>
                            @if ($this->result->truncated)
                                <span class="text-amber-400">Results truncated to the strongest
                                    candidates</span>
                            @endif
                        </div>

                        @forelse ($this->result->candidates as $candidate)
                            <div
                                class="rounded-lg border border-gray-700 bg-gray-900 p-4 shadow-sm"
                                x-data="{ open: false }"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <flux:avatar
                                            circle="circle"
                                            color="auto"
                                            color:seed="{{ $candidate->userId }}"
                                            size="sm"
                                        />
                                        <div class="min-w-0">
                                            @if ($candidate->deleted)
                                                <div class="truncate text-sm font-medium text-gray-100">
                                                    {{ $candidate->name }}</div>
                                                <div class="truncate text-xs text-gray-400">
                                                    Deleted account · ID {{ $candidate->userId }}</div>
                                            @else
                                                <a
                                                    href="{{ $candidate->profileUrl }}"
                                                    target="_blank"
                                                    class="text-sm font-medium text-gray-100 underline hover:text-gray-300"
                                                >{{ $candidate->name }}</a>
                                                <div class="truncate text-xs text-gray-400">
                                                    {{ $candidate->email }} · ID {{ $candidate->userId }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        @if ($candidate->deleted)
                                            <flux:badge
                                                size="sm"
                                                color="zinc"
                                            >Deleted</flux:badge>
                                        @endif
                                        <flux:badge
                                            color="{{ $this->scoreColor($candidate->score) }}"
                                            size="sm"
                                        >
                                            {{ $candidate->score }}/100
                                        </flux:badge>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach ($candidate->matchedSignals as $signal)
                                        <flux:tooltip toggleable>
                                            <flux:badge
                                                size="sm"
                                                color="zinc"
                                                class="cursor-pointer"
                                            >{{ $this->signalLabel($signal) }}</flux:badge>
                                            <flux:tooltip.content class="max-w-xs">
                                                {{ $this->signalDescription($signal) }}
                                            </flux:tooltip.content>
                                        </flux:tooltip>
                                    @endforeach
                                </div>

                                <button
                                    type="button"
                                    x-on:click="open = !open"
                                    class="mt-3 text-xs font-medium text-blue-400 hover:underline"
                                >
                                    <span x-show="!open">Show evidence</span>
                                    <span
                                        x-show="open"
                                        x-cloak
                                    >Hide evidence</span>
                                </button>

                                <div
                                    x-show="open"
                                    x-cloak
                                    class="mt-4 space-y-3 text-sm text-gray-300"
                                >
                                    @if (count($candidate->sharedIps) > 0)
                                        <div class="overflow-hidden rounded-lg border border-gray-700">
                                            <div class="flex items-center gap-2 bg-gray-800/50 px-3 py-2">
                                                <flux:icon.globe-alt class="h-4 w-4 text-gray-500" />
                                                <span
                                                    class="text-xs font-semibold uppercase tracking-wide text-gray-400"
                                                >Shared IP addresses</span>
                                            </div>
                                            <div class="overflow-x-auto">
                                                <table class="w-full text-xs">
                                                    <thead>
                                                        <tr class="border-t border-gray-700 text-left text-gray-500">
                                                            <th class="px-3 py-2 font-medium">IP</th>
                                                            <th class="px-3 py-2 text-right font-medium">Hits</th>
                                                            <th class="px-3 py-2 font-medium">Sources</th>
                                                            <th class="whitespace-nowrap px-3 py-2 font-medium">First
                                                                seen</th>
                                                            <th class="whitespace-nowrap px-3 py-2 font-medium">Last
                                                                seen</th>
                                                            <th class="px-3 py-2 font-medium">Also used by</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-800 border-t border-gray-700">
                                                        @foreach ($candidate->sharedIps as $sharedIp)
                                                            <tr>
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2 align-top font-mono text-gray-100">
                                                                    {{ $sharedIp->ip }}</td>
                                                                <td
                                                                    class="px-3 py-2 text-right align-top tabular-nums">
                                                                    {{ number_format($sharedIp->hits) }}</td>
                                                                <td class="px-3 py-2 align-top">
                                                                    {{ implode(' + ', $sharedIp->sources) }}</td>
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2 align-top font-mono text-gray-400">
                                                                    {{ $sharedIp->firstSeen }}</td>
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2 align-top font-mono text-gray-400">
                                                                    {{ $sharedIp->lastSeen }}</td>
                                                                <td class="px-3 py-2 align-top">
                                                                    @if (count($sharedIp->otherAccounts) > 0)
                                                                        <div class="flex max-w-xs flex-wrap gap-1">
                                                                            @foreach ($sharedIp->otherAccounts as $otherAccount)
                                                                                <span
                                                                                    class="rounded bg-gray-800 px-1.5 py-0.5 text-gray-300"
                                                                                >{{ $otherAccount }}</span>
                                                                            @endforeach
                                                                        </div>
                                                                    @else
                                                                        <span
                                                                            class="italic text-gray-500">Exclusive</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($candidate->sameDomain)
                                        <div class="rounded-lg border border-gray-700 px-3 py-2.5">
                                            <div class="mb-1.5 flex items-center gap-2">
                                                <flux:icon.envelope class="h-4 w-4 text-gray-500" />
                                                <span
                                                    class="text-xs font-semibold uppercase tracking-wide text-gray-400"
                                                >Email domain</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="font-mono text-gray-100">{{ '@' . $candidate->domain }}</span>
                                                @if ($candidate->disposableDomain)
                                                    <flux:badge
                                                        size="sm"
                                                        color="amber"
                                                    >Disposable</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if ($candidate->timeline)
                                        <div class="rounded-lg border border-gray-700 px-3 py-2.5">
                                            <div class="mb-1.5 flex items-center gap-2">
                                                <flux:icon.clock class="h-4 w-4 text-gray-500" />
                                                <span
                                                    class="text-xs font-semibold uppercase tracking-wide text-gray-400"
                                                >Activity timeline</span>
                                            </div>
                                            <div>
                                                {{ $this->timelineLabel($candidate->timeline) }} on
                                                <span
                                                    class="font-mono text-gray-100">{{ $candidate->timeline->ip }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    @if (count($candidate->fingerprintOverlap) > 0)
                                        <div class="rounded-lg border border-gray-700 px-3 py-2.5">
                                            <div class="mb-1.5 flex items-center gap-2">
                                                <flux:icon.finger-print class="h-4 w-4 text-gray-500" />
                                                <span
                                                    class="text-xs font-semibold uppercase tracking-wide text-gray-400"
                                                >Device fingerprint</span>
                                            </div>
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($candidate->fingerprintOverlap as $fingerprint)
                                                    <span
                                                        class="rounded bg-gray-800 px-2 py-1 font-mono text-xs text-gray-300"
                                                    >{{ str_replace('|', ' / ', $fingerprint) }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex flex-wrap gap-2 pt-1">
                                        @unless ($candidate->deleted)
                                            <flux:button
                                                href="{{ $candidate->profileUrl }}"
                                                target="_blank"
                                                size="xs"
                                                variant="outline"
                                                icon="user"
                                            >
                                                View profile
                                            </flux:button>
                                        @endunless
                                        <flux:button
                                            :href="route('admin.user-management')"
                                            wire:navigate
                                            size="xs"
                                            variant="outline"
                                            icon="users"
                                        >
                                            User management
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-lg border border-gray-700 bg-gray-900 p-12 text-center shadow-sm">
                                <flux:icon.finger-print class="mx-auto mb-4 h-12 w-12 text-gray-600" />
                                <p class="text-gray-400">No linked accounts found for this user.</p>
                            </div>
                        @endforelse
                    </div>
                @else
                    <div
                        wire:poll.3s
                        role="status"
                        class="space-y-4"
                    >
                        <div class="flex items-center gap-2 text-sm text-gray-400">
                            <flux:icon.loading class="h-4 w-4" />
                            @if ($this->run?->isProcessing())
                                Analyzing account...
                            @else
                                Queued for analysis...
                            @endif
                        </div>

                        @foreach (range(1, 3) as $placeholder)
                            <div
                                class="rounded-lg border border-gray-700 bg-gray-900 p-4 shadow-sm"
                                aria-hidden="true"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex min-w-0 flex-1 items-center gap-3">
                                        <div class="alt-skeleton-bar h-8 w-8 shrink-0 rounded-full bg-gray-700">
                                        </div>
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <div class="alt-skeleton-bar h-3.5 w-1/3 rounded bg-gray-700">
                                            </div>
                                            <div class="alt-skeleton-bar h-2.5 w-1/2 rounded bg-gray-700">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="alt-skeleton-bar h-5 w-12 shrink-0 rounded-full bg-gray-700">
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    <div class="alt-skeleton-bar h-5 w-20 rounded-full bg-gray-700"></div>
                                    <div class="alt-skeleton-bar h-5 w-24 rounded-full bg-gray-700"></div>
                                    <div class="alt-skeleton-bar h-5 w-16 rounded-full bg-gray-700"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
