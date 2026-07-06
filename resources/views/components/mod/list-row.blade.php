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
                    class="size-14 sm:size-16 rounded-md object-cover"
                >
            @else
                <div class="size-14 sm:size-16 rounded-md bg-gray-800 flex items-center justify-center">
                    <flux:icon.cube-transparent class="size-7 text-gray-600" />
                </div>
            @endif
        </a>

        <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 min-w-0">
            <a
                href="{{ $mod->detail_url }}"
                wire:navigate
                class="font-medium text-gray-100 hover:underline truncate"
            >
                {{ $mod->name }}
            </a>
            @if ($version)
                <span class="text-xs text-gray-400 shrink-0">
                    {{ $version->version }}
                </span>
            @endif
        </div>

        <div class="text-xs text-gray-400 truncate">
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
            <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                @if ($sptBadge)
                    <span
                        class="badge-version {{ $sptBadge->color_class }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap"
                    >
                        <span class="sr-only">{{ __('SPT version') }}&nbsp;</span>{{ $sptBadge->version_formatted }}
                    </span>
                @elseif ($version && $version->spt_version_constraint === '')
                    <span
                        class="badge-version gray inline-flex items-center rounded-md px-2 py-1 text-xs font-medium whitespace-nowrap"
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
                            <div class="text-xs space-y-1">
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

        <div class="shrink-0 flex items-center gap-1">
            {{ $slot }}
        </div>
    </div>

    {{ $note ?? '' }}
</div>
