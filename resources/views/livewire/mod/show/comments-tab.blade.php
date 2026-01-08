<div id="comments">
    @if ($mod->comments_disabled && (auth()->user()?->isModOrAdmin() || $mod->isAuthorOrOwner(auth()->user())))
        <div class="mb-6">
            <flux:callout
                icon="exclamation-triangle"
                color="orange"
                inline="inline"
            >
                <flux:callout.text>
                    {{ __('Comments have been disabled for this mod and are not visible to normal users. As :role, you can still view and manage all comments.', ['role' => auth()->user()?->isModOrAdmin() ? 'a staff member or moderator' : 'the mod owner or author']) }}
                </flux:callout.text>
            </flux:callout>
        </div>
    @endif
    <livewire:comment-component
        wire:key="comment-component-{{ $mod->id }}"
        :commentable="$mod"
    />
</div>
