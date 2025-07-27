<div
    x-data="{ pollingInterval: null }"
    x-on:start-spam-check-polling.window="
        pollingInterval = setInterval(() => {
            $wire.pollSpamCheckStatus();
        }, 2000);
        setTimeout(() => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }, 30000);
    "
    x-on:stop-spam-check-polling.window="
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    ">
    <flux:dropdown position="bottom" align="end" class="absolute -top-4.5 -right-4.5">
        <flux:button icon="cog-8-tooth" size="sm" :loading="$this->spamCheckInProgress" />
        <flux:menu>
            @can('viewActions', $comment)
                <flux:menu.group heading="Comment Actions">
                    @can('pin', $comment)
                        <flux:menu.item
                            wire:click="{{ $comment->isPinned() ? 'unpinComment' : 'pinComment' }}"
                            icon:trailing="{{ $comment->isPinned() ? 'bookmark-slash' : 'bookmark' }}">
                            {{ $comment->isPinned() ? 'Unpin Comment' : 'Pin Comment' }}
                        </flux:menu.item>
                    @endcan
                    @if ($comment->isDeleted())
                        @can('restore', $comment)
                            <flux:menu.item x-on:click.prevent="$wire.confirmCommentRestore = true" icon:trailing="arrow-path">Restore Comment</flux:menu.item>
                        @endcan
                        @can('hardDelete', $comment)
                            <flux:menu.item x-on:click.prevent="$wire.confirmCommentHardDelete = true" icon:trailing="trash" variant="danger">Hard Delete Thread</flux:menu.item>
                        @endcan
                    @else
                        @can('softDelete', $comment)
                            @unless($comment->isSpam())
                                <flux:menu.item x-on:click.prevent="$wire.confirmCommentSoftDelete = true" icon:trailing="eye-slash">Soft Delete</flux:menu.item>
                            @endunless
                        @endcan
                        @can('hardDelete', $comment)
                            <flux:menu.item x-on:click.prevent="$wire.confirmCommentHardDelete = true" icon:trailing="trash" variant="danger">Hard Delete Thread</flux:menu.item>
                        @endcan
                    @endif
                    @can('markAsSpam', $comment)
                        <flux:menu.item x-on:click.prevent="$wire.confirmCommentMarkSpam = true" icon:trailing="shield-exclamation" variant="danger">Mark as Spam</flux:menu.item>
                    @endcan
                    @can('markAsHam', $comment)
                        <flux:menu.item x-on:click.prevent="$wire.confirmCommentMarkHam = true" icon:trailing="shield-check">Mark as Clean</flux:menu.item>
                    @endcan
                    @can('checkForSpam', $comment)
                        <flux:menu.item
                            x-on:click.prevent="$wire.confirmCommentCheckSpam = true"
                            icon:trailing="magnifying-glass"
                            :disabled="$this->spamCheckInProgress">
                            {{ $this->spamCheckInProgress ? 'Checking...' : 'Check for Spam' }}
                        </flux:menu.item>
                    @endcan
                </flux:menu.group>
            @endcan
        </flux:menu>
    </flux:dropdown>

    <x-confirmation-modal wire:model.live="confirmCommentSoftDelete">
        <x-slot name="title">
            {{ __('Soft Delete Comment') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to soft delete this comment?</p>
            <p class="my-2.5">The comment will be hidden from regular users but will remain visible to moderators and administrators. The comment can potentially be restored later.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button x-on:click="$wire.confirmCommentSoftDelete = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="softDelete" wire:loading.attr="disabled">
                {{ __('Soft Delete') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmCommentHardDelete">
        <x-slot name="title">
            {{ __('Hard Delete Comment Thread') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to permanently delete this comment{{ $comment->isRoot() ? ' and all its replies' : '' }}?</p>
            @if ($comment->isRoot() && $comment->descendants->count() > 0)
                <p class="my-2.5 font-bold text-red-600">
                    Warning: This is a root comment with {{ $comment->descendants->count() }} {{ $comment->descendants->count() === 1 ? 'reply' : 'replies' }}. All replies will also be permanently deleted.
                </p>
            @endif
            <p class="my-2.5">This action cannot be undone.{{ !$comment->isDeleted() ? ' If you\'re unsure, consider using soft delete instead.' : '' }}</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button x-on:click="$wire.confirmCommentHardDelete = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="hardDeleteThread" wire:loading.attr="disabled">
                {{ __('Hard Delete') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmCommentMarkSpam">
        <x-slot name="title">
            {{ __('Mark Comment as Spam') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to manually mark this comment as spam?</p>
            <p class="my-2.5">The comment will be immediately hidden from regular users and flagged as spam. This is a manual moderation action that does not use the Akismet API.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button x-on:click="$wire.confirmCommentMarkSpam = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="markAsSpam" wire:loading.attr="disabled">
                {{ __('Mark as Spam') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmCommentMarkHam">
        <x-slot name="title">
            {{ __('Mark Comment as Clean') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to mark this comment as clean (not spam)?</p>
            <p class="my-2.5">The comment will be made visible to regular users and flagged as legitimate content in the spam detection system. This action helps train the automatic spam detection.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button x-on:click="$wire.confirmCommentMarkHam = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="markAsHam" wire:loading.attr="disabled">
                {{ __('Mark as Clean') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmCommentRestore">
        <x-slot name="title">
            {{ __('Restore Comment') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to restore this comment?</p>
            <p class="my-2.5">The comment will be made visible to regular users again and will function normally.</p>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button x-on:click="$wire.confirmCommentRestore = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="restore" wire:loading.attr="disabled">
                {{ __('Restore') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>

    <x-confirmation-modal wire:model.live="confirmCommentCheckSpam">
        <x-slot name="title">
            {{ __('Check Comment for Spam') }}
        </x-slot>

        <x-slot name="content">
            <p class="my-2.5">Are you sure you want to check this comment for spam using Akismet?</p>
            <p class="my-2.5">This will queue a background job to analyze the comment using the Akismet API. The results will update the comment's spam status automatically based on the API response.</p>
            @if (!$comment->canBeRechecked())
                <p class="my-2.5 font-bold text-amber-600">
                    Note: This comment has already reached the maximum number of spam check attempts.
                </p>
            @endif
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button x-on:click="$wire.confirmCommentCheckSpam = false" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-danger-button class="ms-3" wire:click="checkForSpam" wire:loading.attr="disabled" :disabled="$this->spamCheckInProgress">
                {{ $this->spamCheckInProgress ? __('Checking...') : __('Check for Spam') }}
            </x-danger-button>
        </x-slot>
    </x-confirmation-modal>
</div>
