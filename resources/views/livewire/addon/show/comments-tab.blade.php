<div id="comments">
    @if ($addon->comments_disabled && (auth()->user()?->isModOrAdmin() || $addon->isAuthorOrOwner(auth()->user())))
        <div class="mb-6">
            <flux:callout
                icon="exclamation-triangle"
                color="orange"
                inline="inline"
            >
                <flux:callout.text>
                    {{ __('Comments have been disabled for this addon and are not visible to normal users. As :role, you can still view and manage all comments.', ['role' => auth()->user()?->isModOrAdmin() ? 'an administrator or moderator' : 'the addon owner or author']) }}
                </flux:callout.text>
            </flux:callout>
        </div>
    @endif
    <livewire:comment-component
        wire:key="comment-component-{{ $addon->id }}"
        :commentable="$addon"
    />
</div>
