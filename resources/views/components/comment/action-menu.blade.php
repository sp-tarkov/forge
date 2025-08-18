@props(['comment', 'descendantsCount' => null])

<div x-data="{ 
    menuOpen: false, 
    activeModal: null,
    spamCheckPolling: null,
    
    startPolling(commentId) {
        this.stopPolling();
        this.spamCheckPolling = setInterval(() => {
            $wire.pollSpamCheckStatus(commentId);
        }, 2000); // Poll every 2 seconds
    },
    
    stopPolling() {
        if (this.spamCheckPolling) {
            clearInterval(this.spamCheckPolling);
            this.spamCheckPolling = null;
        }
    }
}" 
x-on:start-spam-check-polling.window="startPolling($event.detail[0])"
x-on:stop-spam-check-polling.window="stopPolling()"
x-on:beforeunload.window="stopPolling()">
    <flux:dropdown position="bottom" align="end" class="absolute -top-4.5 -right-4.5">
        <flux:button
            icon="cog-8-tooth"
            size="sm"
        />
        <flux:menu class="action-comments">
            @if(\App\Support\CachedGate::allows('viewActions', $comment))
                <flux:menu.group heading="Comment Actions">
                    @if(\App\Support\CachedGate::allows('pin', $comment))
                        @if($comment->isPinned())
                            <flux:menu.item
                                wire:click="confirmUnpinComment({{ $comment->id }})"
                                icon:trailing="bookmark-slash"
                                class="action-unpin">
                                Unpin Comment
                            </flux:menu.item>
                        @else
                            <flux:menu.item
                                wire:click="confirmPinComment({{ $comment->id }})"
                                icon:trailing="bookmark"
                                class="action-pin">
                                Pin Comment
                            </flux:menu.item>
                        @endif
                    @endif
                    
                    @if ($comment->isDeleted())
                        @if(\App\Support\CachedGate::allows('restore', $comment))
                            <flux:menu.item 
                                wire:click="confirmRestoreComment({{ $comment->id }})" 
                                icon:trailing="arrow-path">
                                Restore Comment
                            </flux:menu.item>
                        @endif
                        @if(\App\Support\CachedGate::allows('hardDelete', $comment))
                            <flux:menu.item 
                                wire:click="confirmHardDeleteComment({{ $comment->id }})" 
                                icon:trailing="trash" 
                                variant="danger">
                                Hard Delete Thread
                            </flux:menu.item>
                        @endif
                    @else
                        @if(\App\Support\CachedGate::allows('softDelete', $comment))
                            @unless($comment->isSpam())
                                <flux:menu.item 
                                    wire:click="confirmSoftDeleteComment({{ $comment->id }})" 
                                    icon:trailing="eye-slash">
                                    Soft Delete
                                </flux:menu.item>
                            @endunless
                        @endif
                        @if(\App\Support\CachedGate::allows('hardDelete', $comment))
                            <flux:menu.item 
                                wire:click="confirmHardDeleteComment({{ $comment->id }})" 
                                icon:trailing="trash" 
                                variant="danger">
                                Hard Delete Thread
                            </flux:menu.item>
                        @endif
                    @endif
                    
                    @if(\App\Support\CachedGate::allows('markAsSpam', $comment))
                        <flux:menu.item 
                            wire:click="confirmMarkAsSpam({{ $comment->id }})" 
                            icon:trailing="shield-exclamation" 
                            variant="danger">
                            Mark as Spam
                        </flux:menu.item>
                    @endif
                    
                    @if(\App\Support\CachedGate::allows('markAsHam', $comment))
                        <flux:menu.item 
                            wire:click="confirmMarkAsClean({{ $comment->id }})" 
                            icon:trailing="shield-check">
                            Mark as Clean
                        </flux:menu.item>
                    @endif
                    
                    @if(\App\Support\CachedGate::allows('checkForSpam', $comment))
                        <flux:menu.item
                            wire:click="confirmCheckForSpam({{ $comment->id }})"
                            icon:trailing="magnifying-glass">
                            Check for Spam
                        </flux:menu.item>
                    @endif
                </flux:menu.group>
            @endif
        </flux:menu>
    </flux:dropdown>
</div>