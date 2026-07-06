<div
    @if ($wireKey) wire:key="{{ $wireKey }}" @endif
    {{ $attributes->merge(['class' => 'p-3 sm:p-4']) }}
>
    <div class="flex items-start gap-3">
        <a
            href="{{ $mod->detail_url }}"
            wire:navigate
            class="shrink-0"
            aria-hidden="true"
            tabindex="-1"
        >
            @if ($mod->thumbnail)
                <img
                    src="{{ $mod->thumbnailUrl }}"
                    alt=""
                    class="size-14 rounded-md object-cover sm:size-16"
                >
            @else
                <div class="flex size-14 items-center justify-center rounded-md bg-gray-800 sm:size-16">
                    <flux:icon.cube-transparent class="size-7 text-gray-600" />
                </div>
            @endif
        </a>

        <div class="min-w-0 flex-1">
            <div class="flex min-w-0 items-center gap-2">
                <a
                    href="{{ $mod->detail_url }}"
                    wire:navigate
                    class="truncate font-medium text-gray-100 hover:underline"
                >
                    {{ $mod->name }}
                </a>
                @if ($version)
                    <span class="shrink-0 text-xs text-gray-400">
                        {{ $version->version }}
                    </span>
                @endif
            </div>

            <div class="truncate text-xs text-gray-400">
                {{ __('by :owner', ['owner' => $mod->owner?->name ?? __('Unknown')]) }}
                <span aria-hidden="true">·</span>
                <span title="{{ Number::format($mod->downloads) }} {{ __(Str::plural('Download', $mod->downloads)) }}">
                    {{ Number::downloads($mod->downloads) }}
                    {{ __(Str::plural('download', $mod->downloads)) }}
                </span>
                @if ($mod->updated_at)
                    <span aria-hidden="true">·</span>
                    <x-time :datetime="$mod->updated_at" />
                @endif
            </div>

            @if ($hasSptBadge() || $isDependency || $hasDependencyBadge() || $showIncompatibleIndicator())
                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                    @if ($sptBadge)
                        <span
                            class="badge-version {{ $sptBadge->color_class }} inline-flex items-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium"
                        >
                            <span
                                class="sr-only">{{ __('SPT version') }}&nbsp;</span>{{ $sptBadge->version_formatted }}
                        </span>
                    @elseif ($version && $version->spt_version_constraint === '')
                        <span
                            class="badge-version gray inline-flex items-center whitespace-nowrap rounded-md px-2 py-1 text-xs font-medium"
                        >
                            {{ __('Legacy SPT') }}
                        </span>
                    @endif
                    @if ($isDependency)
                        <flux:badge
                            size="sm"
                            color="zinc"
                            icon="link"
                        >
                            {{ __('Dependency') }}
                        </flux:badge>
                    @endif
                    @if ($hasDependencyBadge())
                        <flux:tooltip>
                            <flux:badge
                                size="sm"
                                :color="$dependenciesSatisfied() ? 'emerald' : 'red'"
                                :icon="$dependenciesSatisfied() ? 'check' : 'x-mark'"
                            >
                                {{ $dependencyBadgeLabel() }}
                            </flux:badge>
                            <flux:tooltip.content>
                                <div class="space-y-1 text-xs">
                                    @foreach ($dependencyVersions as $depVersion)
                                        <div class="flex items-center gap-1">
                                            @if ($dependencyOnList($depVersion))
                                                <flux:icon.check class="size-3 text-emerald-500" />
                                                <span class="sr-only">{{ __('On list:') }}</span>
                                            @else
                                                <flux:icon.x-mark class="size-3 text-red-500" />
                                                <span class="sr-only">{{ __('Missing:') }}</span>
                                            @endif
                                            <span>{{ $depVersion->mod?->name }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </flux:tooltip.content>
                        </flux:tooltip>
                    @endif
                    @if ($showIncompatibleIndicator())
                        <flux:tooltip>
                            <flux:badge
                                size="sm"
                                color="amber"
                                icon="exclamation-triangle"
                            >
                                {{ __('Not compatible') }}
                            </flux:badge>
                            <flux:tooltip.content>
                                <div class="text-xs">
                                    {{ __("No version of this mod matches the list's target SPT version. The closest available version is shown.") }}
                                </div>
                            </flux:tooltip.content>
                        </flux:tooltip>
                    @endif
                </div>
            @endif

        </div>

        <div class="flex shrink-0 items-center gap-1">
            {{ $slot }}
        </div>
    </div>

    {{ $note ?? '' }}
</div>
