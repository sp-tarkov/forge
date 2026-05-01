@props([
    'mod',
    'version' => null,
    'note' => null,
    'dependencyVersions' => null,
    'isDependency' => false,
    'listModIds' => null,
    'wireKey' => null,
])

@php($version ??= $mod->latestVersion)

<div
    @if ($wireKey) wire:key="{{ $wireKey }}" @endif
    {{ $attributes->merge(['class' => 'flex items-start gap-3 p-3 sm:p-4']) }}
>
    <a
        href="{{ $mod->detail_url }}"
        wire:navigate
        class="shrink-0"
    >
        @if ($mod->thumbnail)
            <img
                src="{{ $mod->thumbnailUrl }}"
                alt="{{ $mod->name }}"
                class="size-14 sm:size-16 rounded-md object-cover"
            >
        @else
            <div class="size-14 sm:size-16 rounded-md bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                <flux:icon.cube-transparent class="size-7 text-gray-400 dark:text-gray-600" />
            </div>
        @endif
    </a>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 min-w-0">
            <a
                href="{{ $mod->detail_url }}"
                wire:navigate
                class="font-medium text-gray-900 dark:text-gray-100 hover:underline truncate"
            >
                {{ $mod->name }}
            </a>
            @if ($version)
                <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                    {{ $version->version }}
                </span>
            @endif
        </div>

        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
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

        @php($hasSptBadge = $version?->latestSptVersion || ($version && $version->spt_version_constraint === ''))
        @php($hasDepsBadge = $dependencyVersions && $dependencyVersions->isNotEmpty())
        @if ($hasSptBadge || $isDependency || $hasDepsBadge)
            <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                @if ($version?->latestSptVersion)
                    <span
                        class="badge-version {{ $version->latestSptVersion->color_class }} inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap"
                    >
                        {{ $version->latestSptVersion->version_formatted }}
                    </span>
                @elseif ($version && $version->spt_version_constraint === '')
                    <span
                        class="badge-version gray inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap"
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
                @if ($hasDepsBadge)
                    @php($depCount = $dependencyVersions->count())
                    @php($missingDeps = $listModIds
                        ? $dependencyVersions->reject(fn ($depVersion) => $listModIds->contains($depVersion->mod_id))
                        : $dependencyVersions)
                    @php($depsAllSatisfied = $missingDeps->isEmpty())
                    <flux:tooltip>
                        <flux:badge
                            size="sm"
                            :color="$depsAllSatisfied ? 'emerald' : 'red'"
                            :icon="$depsAllSatisfied ? 'check' : 'x-mark'"
                        >
                            {{ trans_choice(':count dependency|:count dependencies', $depCount, ['count' => $depCount]) }}
                        </flux:badge>
                        <flux:tooltip.content>
                            <div class="text-xs space-y-1">
                                @foreach ($dependencyVersions as $depVersion)
                                    @php($inList = $listModIds && $listModIds->contains($depVersion->mod_id))
                                    <div class="flex items-center gap-1">
                                        @if ($inList)
                                            <flux:icon.check class="size-3 text-emerald-500" />
                                        @else
                                            <flux:icon.x-mark class="size-3 text-red-500" />
                                        @endif
                                        <span>{{ $depVersion->mod?->name }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </flux:tooltip.content>
                    </flux:tooltip>
                @endif
            </div>
        @endif

        @if ($note)
            <div class="mt-1.5 text-xs italic text-gray-600 dark:text-gray-400 line-clamp-2">
                <flux:icon.chat-bubble-left class="inline size-3 mr-0.5 text-gray-400" />
                {{ $note }}
            </div>
        @endif
    </div>

    <div class="shrink-0 flex items-center gap-1">
        {{ $slot }}
    </div>
</div>
