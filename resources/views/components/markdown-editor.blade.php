@props([
    'wireModel',
    'name',
    'label' => null,
    'description' => null,
    'placeholder' => '',
    'rows' => 6,
    'purifyConfig' => 'description',
    'errorName' => null,
])

<div
    x-data="{
        activeTab: 'write',
        previewHtml: '',
        isLoadingPreview: false,
        content: @entangle($wireModel).live,
        async switchToPreview() {
            this.activeTab = 'preview';
            this.isLoadingPreview = true;
            try {
                this.previewHtml = await $wire.previewMarkdown(this.content, '{{ $purifyConfig }}');
                // Wait for DOM to update, then initialize tabs
                await this.$nextTick();
                // Dispatch event to initialize tabsets
                this.$dispatch('content-updated');
            } catch (error) {
                console.error('Preview error:', error);
                this.previewHtml = '<p class=\'text-red-500\'>' + '{{ __('Error generating preview.') }}' + '</p>';
            } finally {
                this.isLoadingPreview = false;
            }
        },
        switchToWrite() {
            this.activeTab = 'write';
        }
    }"
    class="space-y-2"
    wire:key="markdown-editor-{{ $name }}"
>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    {{-- Tab Navigation --}}
    <div
        class="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700"
        role="tablist"
    >
        <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'write'"
            tabindex="0"
            @click="switchToWrite"
            :class="{
                'border-cyan-500 dark:border-cyan-600 text-slate-900 dark:text-white': activeTab === 'write',
                'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300': activeTab !== 'write'
            }"
            class="px-4 py-2 text-sm font-medium border-b-2 rounded-t-lg transition-colors focus:outline-none focus:bg-slate-100 dark:focus:bg-slate-800"
        >
            {{ __('Write') }}
        </button>
        <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'preview'"
            tabindex="0"
            @click="switchToPreview"
            :class="{
                'border-cyan-500 dark:border-cyan-600 text-slate-900 dark:text-white': activeTab === 'preview',
                'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300': activeTab !== 'preview'
            }"
            class="px-4 py-2 text-sm font-medium border-b-2 rounded-t-lg transition-colors focus:outline-none focus:bg-slate-100 dark:focus:bg-slate-800"
        >
            {{ __('Preview') }}
        </button>
    </div>

    {{-- Content Area --}}
    <div class="relative">
        {{-- Write Tab --}}
        <div
            x-show="activeTab === 'write'"
            x-cloak
            role="tabpanel"
            :aria-hidden="activeTab !== 'write'"
        >
            <flux:textarea
                name="{{ $name }}"
                wire:model="{{ $wireModel }}"
                rows="{{ $rows }}"
                placeholder="{{ $placeholder }}"
                {{ $attributes }}
            />
        </div>

        {{-- Preview Tab --}}
        <div
            x-show="activeTab === 'preview'"
            x-cloak
            role="tabpanel"
            :aria-hidden="activeTab !== 'preview'"
            class="min-h-[{{ $rows * 1.5 }}rem] py-3 px-4 sm:py-4 sm:px-6 bg-gray-50 dark:bg-white/10 rounded-xl border border-slate-200 dark:border-slate-700"
        >
            <div
                x-show="isLoadingPreview"
                class="flex items-center justify-center py-8"
            >
                <svg
                    class="animate-spin h-8 w-8 text-cyan-500"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
            </div>
            <div
                x-show="!isLoadingPreview"
                x-html="previewHtml"
                class="user-markdown"
            ></div>
        </div>
    </div>

    <flux:error name="{{ $errorName ?? $name }}" />
</div>
