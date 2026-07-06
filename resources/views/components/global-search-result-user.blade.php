<a
    href="/user/{{ $result['id'] }}/{{ Str::slug($result['name']) }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="{{ $tabindex ?? 0 }}"
>
    <div class="size-8 shrink-0 self-center">
        <flux:avatar
            src="{{ $result['profile_photo_url'] ?? '' }}"
            color="auto"
            color:seed="{{ $result['id'] }}"
            circle="circle"
            class="size-8"
        />
    </div>
    <div class="flex min-w-0 grow flex-col">
        <span class="truncate text-sm font-medium">{{ $result['name'] }}</span>
        @if (($result['mods_count'] ?? 0) > 0 || ($result['addons_count'] ?? 0) > 0)
            <span class="text-xs text-gray-400">
                @if (($result['mods_count'] ?? 0) > 0)
                    {{ $result['mods_count'] }} {{ Str::plural('mod', $result['mods_count']) }}
                @endif
                @if (($result['mods_count'] ?? 0) > 0 && ($result['addons_count'] ?? 0) > 0)
                    &middot;
                @endif
                @if (($result['addons_count'] ?? 0) > 0)
                    {{ $result['addons_count'] }} {{ Str::plural('addon', $result['addons_count']) }}
                @endif
            </span>
        @endif
    </div>
</a>
