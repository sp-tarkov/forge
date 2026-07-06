@props(['formKey', 'submitAction', 'placeholder', 'submitText', 'cancelAction' => null, 'dataTest' => null])

<form
    x-data="{ hasLogFile: false }"
    @log-file-detected.window="hasLogFile = $event.detail.containsLogFile"
    @submit.prevent="!hasLogFile && $wire.{{ $submitAction }}"
>
    <x-honeypot livewire-model="honeypotData" />
    <x-markdown-editor
        wire-model="{{ $formKey }}"
        name="body"
        :placeholder="$placeholder ?? __('Please ensure your comment does not break the community guidelines.')"
        rows="4"
        purify-config="comments"
        error-name="{{ $formKey }}"
        data-test="{{ $dataTest ?? str_replace('.', '-', $formKey) }}"
        :show-update-request-warning="true"
    />

    <div class="mt-2 flex items-center justify-between">
        @if ($cancelAction)
            <div class="flex items-center gap-2">
                <flux:button
                    variant="primary"
                    size="sm"
                    class="bg-cyan-700 text-white hover:bg-cyan-600"
                    type="submit"
                    :loading="false"
                    ::disabled="hasLogFile"
                    ::class="{ 'opacity-50 cursor-not-allowed': hasLogFile }"
                >
                    <span
                        x-show="!hasLogFile"
                        wire:loading
                        wire:target="{{ $submitAction }}"
                    >
                        <flux:icon.loading class="size-5" />
                    </span>
                    <span
                        x-show="!hasLogFile"
                        wire:loading.remove
                        wire:target="{{ $submitAction }}"
                    >{{ $submitText }}</span>
                    <span
                        x-show="hasLogFile"
                        x-cloak
                    >{{ $submitText }}</span>
                </flux:button>
                <flux:button
                    type="button"
                    wire:click="{{ $cancelAction }}"
                    data-test="cancel-{{ $dataTest ?? str_replace('.', '-', $formKey) }}"
                    variant="ghost"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
            </div>
            <div class="ml-2 text-right text-xs text-slate-400">
                {{ __('Basic Markdown formatting is supported.') }}
            </div>
        @else
            <flux:button
                variant="primary"
                size="sm"
                class="bg-cyan-700 text-white hover:bg-cyan-600"
                type="submit"
                :loading="false"
                ::disabled="hasLogFile"
                ::class="{ 'opacity-50 cursor-not-allowed': hasLogFile }"
            >
                <span
                    x-show="!hasLogFile"
                    wire:loading
                    wire:target="{{ $submitAction }}"
                >
                    <flux:icon.loading class="size-5" />
                </span>
                <span
                    x-show="!hasLogFile"
                    wire:loading.remove
                    wire:target="{{ $submitAction }}"
                >{{ $submitText }}</span>
                <span
                    x-show="hasLogFile"
                    x-cloak
                >{{ $submitText }}</span>
            </flux:button>
            <div class="ml-2 text-right text-xs text-slate-400">
                {{ __('Basic Markdown formatting is supported.') }}
            </div>
        @endif
    </div>
</form>
