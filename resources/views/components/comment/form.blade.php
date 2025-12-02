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

    <div class="flex items-center justify-between mt-2">
        @if ($cancelAction)
            <div class="flex items-center gap-2">
                <flux:button
                    variant="primary"
                    size="sm"
                    class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
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
            <div class="text-xs text-slate-400 text-right ml-2">
                {{ __('Basic Markdown formatting is supported.') }}
            </div>
        @else
            <flux:button
                variant="primary"
                size="sm"
                class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
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
            <div class="text-xs text-slate-400 text-right ml-2">
                {{ __('Basic Markdown formatting is supported.') }}
            </div>
        @endif
    </div>
</form>
