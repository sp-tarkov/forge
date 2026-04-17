<x-slot:title>
    {{ __('Spam Review - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Review and action comments flagged as spam.') }}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
        {{ __('Spam Review') }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div
            wire:poll.30s="$refresh"
            class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg"
        >
            <div class="p-6">
                <div class="mb-6 pb-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3
                                id="spam-review"
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >{{ __('Spam Review') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Review comments flagged as spam by Akismet and confirm, approve, or delete them.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-4 flex-shrink-0">
                            @if ($this->pendingSpamCount > 0)
                                <flux:badge
                                    color="yellow"
                                    size="sm"
                                >
                                    {{ $this->pendingSpamCount }} {{ __('Pending Review') }}
                                </flux:badge>
                            @else
                                <flux:badge
                                    color="gray"
                                    size="sm"
                                >{{ __('Queue Empty') }}</flux:badge>
                            @endif
                        </div>
                    </div>

                    {{-- Filters --}}
                    <div class="mt-4 flex flex-wrap items-end gap-4">
                        <div class="w-48">
                            <flux:select
                                wire:model.live="filterType"
                                label="{{ __('Content type') }}"
                                size="sm"
                            >
                                <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                                <flux:select.option value="mod">{{ __('Mod') }}</flux:select.option>
                                <flux:select.option value="addon">{{ __('Addon') }}</flux:select.option>
                                <flux:select.option value="user">{{ __('User profile') }}</flux:select.option>
                            </flux:select>
                        </div>
                        <div class="w-48">
                            <flux:input
                                wire:model.live.debounce.300ms="filterAuthor"
                                label="{{ __('Author') }}"
                                placeholder="{{ __('Username...') }}"
                                size="sm"
                            />
                        </div>
                        @if ($filterType !== '' || $filterAuthor !== '')
                            <flux:button
                                wire:click="clearFilters"
                                variant="ghost"
                                size="sm"
                            >
                                {{ __('Clear filters') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                @if ($this->comments->count() > 0)
                    @if ($this->comments->hasPages())
                        <div class="mb-6">
                            {{ $this->comments->links(data: ['scrollTo' => '#spam-review']) }}
                        </div>
                    @endif

                    <div class="space-y-4">
                        @foreach ($this->comments as $comment)
                            <div
                                class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-4 sm:p-6"
                            >
                                {{-- Spam metadata badges --}}
                                <div class="mb-4 flex flex-wrap items-center gap-2">
                                    <flux:badge
                                        color="red"
                                        size="sm"
                                        icon="shield-exclamation"
                                    >
                                        {{ __('Spam') }}
                                    </flux:badge>
                                    @if (is_array($comment->spam_metadata) && ($comment->spam_metadata['pro_tip'] ?? null) === 'discard')
                                        <flux:badge
                                            color="red"
                                            size="sm"
                                        >
                                            {{ __('Akismet: discard') }}
                                        </flux:badge>
                                    @endif
                                    @if ($comment->spam_checked_at)
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ __('Checked') }} {{ $comment->spam_checked_at->diffForHumans() }}
                                        </span>
                                    @endif
                                    <flux:badge
                                        color="zinc"
                                        size="sm"
                                    >
                                        #{{ $comment->id }}
                                    </flux:badge>
                                </div>

                                {{-- Root-comment styled card --}}
                                <x-comment.card :comment="$comment" />

                                {{-- Action bar --}}
                                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
                                    @if ($comment->getUrl())
                                        <a
                                            href="{{ $comment->getUrl() }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300"
                                        >
                                            <flux:icon.link class="size-4" />
                                            <span>{{ __('View in context') }}</span>
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400">{{ __('No context link available') }}</span>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-2">
                                        @can('confirmSpam', $comment)
                                            <flux:button
                                                size="xs"
                                                variant="danger"
                                                icon="shield-exclamation"
                                                wire:click="openActionModal({{ $comment->id }}, 'confirm_spam')"
                                            >
                                                {{ __('Confirm Spam') }}
                                            </flux:button>
                                        @endcan

                                        @can('markAsHam', $comment)
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                icon="shield-check"
                                                wire:click="openActionModal({{ $comment->id }}, 'mark_as_ham')"
                                            >
                                                {{ __('Not Spam') }}
                                            </flux:button>
                                        @endcan

                                        @can('softDelete', $comment)
                                            <flux:button
                                                size="xs"
                                                variant="outline"
                                                icon="trash"
                                                wire:click="openActionModal({{ $comment->id }}, 'soft_delete')"
                                            >
                                                {{ __('Soft Delete') }}
                                            </flux:button>
                                        @endcan

                                        @can('hardDelete', $comment)
                                            <flux:button
                                                size="xs"
                                                variant="danger"
                                                icon="fire"
                                                wire:click="openActionModal({{ $comment->id }}, 'hard_delete')"
                                            >
                                                {{ __('Hard Delete') }}
                                            </flux:button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->comments->hasPages())
                        <div class="mt-6">
                            {{ $this->comments->links(data: ['scrollTo' => '#spam-review']) }}
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <flux:icon.shield-check
                            size="xl"
                            class="mx-auto text-gray-400"
                        />
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ __('Queue is empty') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('There are no comments awaiting spam review.') }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Action Confirmation Modal --}}
            <flux:modal
                wire:model="showActionModal"
                class="md:w-[500px]"
            >
                <div class="space-y-0">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                        <div class="flex items-center gap-3">
                            @if ($selectedAction === 'confirm_spam')
                                <flux:icon
                                    name="shield-exclamation"
                                    class="w-8 h-8 text-red-600"
                                />
                            @elseif ($selectedAction === 'mark_as_ham')
                                <flux:icon
                                    name="shield-check"
                                    class="w-8 h-8 text-green-600"
                                />
                            @elseif ($selectedAction === 'soft_delete')
                                <flux:icon
                                    name="trash"
                                    class="w-8 h-8 text-amber-600"
                                />
                            @elseif ($selectedAction === 'hard_delete')
                                <flux:icon
                                    name="fire"
                                    class="w-8 h-8 text-red-600"
                                />
                            @endif
                            <div>
                                <flux:heading
                                    size="xl"
                                    class="text-gray-900 dark:text-gray-100"
                                >
                                    @if ($selectedAction === 'confirm_spam')
                                        {{ __('Confirm Spam') }}
                                    @elseif ($selectedAction === 'mark_as_ham')
                                        {{ __('Not Spam') }}
                                    @elseif ($selectedAction === 'soft_delete')
                                        {{ __('Soft Delete') }}
                                    @elseif ($selectedAction === 'hard_delete')
                                        {{ __('Hard Delete') }}
                                    @endif
                                </flux:heading>
                                <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                    @if ($selectedAction === 'confirm_spam')
                                        {{ __('Submit positive feedback to the spam filter so future similar comments are flagged.') }}
                                    @elseif ($selectedAction === 'mark_as_ham')
                                        {{ __('Approve the comment and submit negative feedback to the spam filter.') }}
                                    @elseif ($selectedAction === 'soft_delete')
                                        {{ __('Hide this comment from the site. It can be restored later.') }}
                                    @elseif ($selectedAction === 'hard_delete')
                                        {{ __('Permanently delete this comment and any replies. This cannot be undone.') }}
                                    @endif
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        @if ($selectedAction === 'hard_delete')
                            <div
                                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4"
                            >
                                <div class="flex items-start gap-3">
                                    <flux:icon
                                        name="exclamation-triangle"
                                        class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0"
                                    />
                                    <div>
                                        <flux:text class="text-red-800 dark:text-red-200 text-sm font-medium">
                                            {{ __('Warning') }}
                                        </flux:text>
                                        <flux:text class="text-red-700 dark:text-red-300 text-sm mt-1">
                                            {{ __('The comment and every reply beneath it will be permanently destroyed.') }}
                                        </flux:text>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <flux:textarea
                            wire:model="actionNote"
                            label="{{ __('Reason (optional)') }}"
                            placeholder="{{ __('Explain why you are taking this action...') }}"
                            rows="3"
                        />
                    </div>

                    <div
                        class="flex justify-end items-center gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700"
                    >
                        <flux:button
                            wire:click="$set('showActionModal', false)"
                            variant="outline"
                            size="sm"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            wire:click="executeAction"
                            variant="{{ $selectedAction === 'mark_as_ham' ? 'primary' : 'danger' }}"
                            size="sm"
                        >
                            @if ($selectedAction === 'confirm_spam')
                                {{ __('Confirm Spam') }}
                            @elseif ($selectedAction === 'mark_as_ham')
                                {{ __('Not Spam') }}
                            @elseif ($selectedAction === 'soft_delete')
                                {{ __('Soft Delete') }}
                            @elseif ($selectedAction === 'hard_delete')
                                {{ __('Hard Delete') }}
                            @endif
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    </div>
</div>
