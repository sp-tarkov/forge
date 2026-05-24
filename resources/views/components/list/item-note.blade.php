@props([
    'itemId' => null,
    'note' => null,
    'canManage' => false,
    'editing' => false,
])

@if ($itemId !== null)
    @if ($editing)
        <div
            wire:key="note-editor-{{ $itemId }}"
            class="mt-1.5"
            x-data
            x-init="$nextTick(() => $el.querySelector('textarea')?.focus())"
        >
            <flux:textarea
                wire:model="noteDraft"
                rows="2"
                maxlength="{{ config('mod-lists.validation.note_max') }}"
                :placeholder="__('Add a note for this item…')"
                label:sr-only="{{ __('Item note') }}"
            />
            <flux:error name="noteDraft" />
            <div class="mt-1.5 flex items-center gap-2">
                <flux:button
                    size="sm"
                    variant="primary"
                    wire:click="saveNote"
                >
                    {{ __('Save') }}
                </flux:button>
                <flux:button
                    size="sm"
                    variant="ghost"
                    wire:click="cancelEditingNote"
                >
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    @elseif (filled($note))
        <div
            wire:key="note-display-{{ $itemId }}"
            class="mt-1.5 flex items-start gap-1"
        >
            <div class="text-xs italic text-gray-600 dark:text-gray-400 line-clamp-2">
                <flux:icon.chat-bubble-left class="inline size-3 mr-0.5 text-gray-400" />
                {{ $note }}
            </div>
            @if ($canManage)
                <flux:button
                    size="sm"
                    variant="subtle"
                    icon="pencil"
                    square
                    class="shrink-0"
                    :aria-label="__('Edit note')"
                    wire:click="startEditingNote({{ $itemId }})"
                />
            @endif
        </div>
    @elseif ($canManage)
        <button
            type="button"
            wire:key="note-add-{{ $itemId }}"
            wire:click="startEditingNote({{ $itemId }})"
            class="mt-1.5 inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <flux:icon.plus class="size-3" />
            {{ __('Add note') }}
        </button>
    @endif
@endif
