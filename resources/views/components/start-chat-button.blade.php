<flux:button
    :href="route('chat.start', $user)"
    variant="outline"
    :size="$size"
    class="whitespace-nowrap"
>
    <div class="flex items-center">
        <flux:icon.chat-bubble-left-ellipsis
            variant="outline"
            @class([
                'size-3' => $size === 'xs',
                'size-4' => $size !== 'xs',
                'mr-1.5',
            ])
        />
        {{ __('Chat') }}
    </div>
</flux:button>
