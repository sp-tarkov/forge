<div id="wall">
    <livewire:comment-component
        wire:key="comment-component-{{ $user->id }}"
        :commentable="$user"
    />
</div>
