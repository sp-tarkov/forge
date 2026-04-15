<span class="inline-flex items-center gap-1">
    <span class="{{ $class }}">{{ $user->name }}</span>
    @if ($user->role?->icon)
        <flux:tooltip :content="$user->role->name">
            <flux:icon
                :name="$user->role->icon"
                class="w-4 h-4 {{ $iconColorClass() }}"
            />
        </flux:tooltip>
    @endif
</span>
