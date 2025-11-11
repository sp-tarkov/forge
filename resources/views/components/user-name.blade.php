@props(['user', 'class' => ''])

@php
    $role = $user->role ?? null;
    $iconColorClass =
        $role && $role->color_class ? "text-{$role->color_class}-500 dark:text-{$role->color_class}-400" : '';
@endphp

<span class="inline-flex items-center gap-1">
    <span class="{{ $class }}">{{ $user->name }}</span>
    @if ($role && $role->icon)
        <flux:tooltip :content="$role->name">
            <flux:icon
                :name="$role->icon"
                class="w-4 h-4 {{ $iconColorClass }}"
            />
        </flux:tooltip>
    @endif
</span>
