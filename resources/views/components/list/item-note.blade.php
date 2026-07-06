@props([
    'itemId' => null,
    'note' => null,
    'canManage' => false,
    'editing' => false,
    'iconColumnClass' => 'w-14 sm:w-16',
    'marginTopClass' => 'mt-2',
])

@if ($itemId !== null)
    @if ($editing)
        <div
            wire:key="note-editor-{{ $itemId }}"
            class="mt-2"
            x-data
            x-init="$nextTick(() => $el.querySelector('textarea')?.focus())"
        >
            <flux:textarea
                wire:model="noteDraft"
                rows="3"
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
            class="mt-2 flex items-start gap-3"
        >
            <div class="{{ $iconColumnClass }} flex shrink-0 justify-end pt-0.5">
                <flux:icon.chat-bubble-left class="size-3 text-gray-400" />
            </div>
            <div class="flex min-w-0 flex-1 items-start gap-1.5">
                <div class="flex-1 whitespace-pre-line break-words text-xs italic text-gray-400">{{ $note }}
                </div>
                @if ($canManage)
                    <button
                        type="button"
                        wire:click="startEditingNote({{ $itemId }})"
                        class="shrink-0 cursor-pointer rounded p-1 text-gray-400 hover:bg-gray-800 hover:text-gray-200"
                        aria-label="{{ __('Edit note') }}"
                    >
                        <flux:icon.pencil class="size-3" />
                    </button>
                @endif
            </div>
        </div>
    @elseif ($canManage)
        <div
            wire:key="note-add-{{ $itemId }}"
            class="{{ $marginTopClass }} flex items-start gap-3"
        >
            <div class="{{ $iconColumnClass }} shrink-0"></div>
            <button
                type="button"
                wire:click="startEditingNote({{ $itemId }})"
                class="inline-flex cursor-pointer items-center gap-1 text-xs text-gray-400 hover:text-gray-200"
            >
                <flux:icon.plus class="size-3" />
                {{ __('Add note') }}
            </button>
        </div>
    @endif
@endif
