<span class="inline-flex items-center gap-1">
    <span class="{{ $class }}">{{ $user->name }}</span>
    @if ($user->role?->icon)
        <flux:tooltip :content="$user->role->name">
            <flux:icon
                :name="$user->role->icon"
                class="{{ $iconColorClass() }} h-4 w-4"
            />
        </flux:tooltip>
    @endif
</span>
