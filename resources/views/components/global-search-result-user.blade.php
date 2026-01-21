<a
    href="/user/{{ $result['id'] }}/{{ Str::slug($result['name']) }}"
    wire:navigate
    class="{{ $linkClass }}"
    role="listitem"
    tabindex="0"
>
    <div class="h-6 w-6 self-center">
        <flux:avatar
            src="{{ $result['profile_photo_url'] ?? '' }}"
            color="auto"
            color:seed="{{ $result['id'] }}"
            circle="circle"
            class="h-6 w-6"
        />
    </div>
    <div class="grow flex flex-col">
        <p class="font-medium">{{ $result['name'] }}</p>
        @if (($result['mods_count'] ?? 0) > 0 || ($result['addons_count'] ?? 0) > 0)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if (($result['mods_count'] ?? 0) > 0)
                    {{ $result['mods_count'] }} {{ Str::plural('mod', $result['mods_count']) }}
                @endif
                @if (($result['mods_count'] ?? 0) > 0 && ($result['addons_count'] ?? 0) > 0)
                    •
                @endif
                @if (($result['addons_count'] ?? 0) > 0)
                    {{ $result['addons_count'] }} {{ Str::plural('addon', $result['addons_count']) }}
                @endif
            </p>
        @endif
    </div>
</a>
