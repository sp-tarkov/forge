@props(['formKey', 'submitAction', 'placeholder', 'submitText', 'cancelAction' => null, 'dataTest' => null])

<form wire:submit.prevent="{{ $submitAction }}">
    <x-honeypot livewire-model="honeypotData" />
    <flux:textarea
        name="body"
        wire:model="{{ $formKey }}"
        data-test="{{ $dataTest ?? str_replace('.', '-', $formKey) }}"
        resize="vertical"
        placeholder="{{ $placeholder ?? __('Please ensure your comment does not break the community guidelines.') }}"
    />
    @error($formKey)
        <div class="text-red-500 text-xs my-1.5">{{ $message }}</div>
    @enderror
    <div class="flex items-center justify-between mt-2">
        <flux:button
            variant="primary"
            size="sm"
            class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
            type="submit">
            {{ $submitText }}
        </flux:button>
        @if($cancelAction)
            <flux:button
                type="button"
                wire:click="{{ $cancelAction }}"
                variant="danger"
                size="sm">
                {{ __('Cancel') }}
            </flux:button>
        @else
            <div class="text-xs text-slate-400 text-right ml-2">
                {{ __('Basic Markdown formatting is supported.') }}
            </div>
        @endif
    </div>
</form>
