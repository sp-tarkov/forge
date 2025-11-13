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
        containsLogFile: false,
        logFilePattern: null,
        containsUpdateRequest: false,
        updateRequestPattern: null,
        init() {
            this.logFilePattern = new RegExp('(?:\\[(?:Message|Info|Warning|Error)\\s*:\\s+[^\\]]+\\]|\\[\\d{4}-\\d{2}-\\d{2}\\s+\\d{2}:\\d{2}:\\d{2}\\.\\d{3}\\]\\[(?:Info|Debug|Warning|Error)\\]\\[|\\d{4}-\\d{2}-\\d{2}\\s+\\d{2}:\\d{2}:\\d{2}\\.\\d{3}\\s+[+\\-]\\d{2}:\\d{2}\\|\\d+\\.\\d+\\.\\d+\\.\\d+\\.\\d+\\||&quot;_(?:id|tpl)&quot;:\\s*&quot;[0-9a-f]{24}&quot;)');
            this.updateRequestPattern = new RegExp('(?:when\\s+(?:will|can|are|is)(?:\\s+(?:this|the))?(?:\\s+mod)?(?:\\s+be)?|can\\s+(?:you|u|it)|please|pls|plz|any\\s+(?:plans|eta|chance)(?:\\s+to)?|will\\s+there\\s+be|(?:is\\s+this\\s+)?gonna\\s+be|does\\s+(?:this\\s+)?(?:mod\\s+)?(?:work|support))\\s+(?:you\\s+)?(?:update(?:d)?|port(?:ed)?|support(?:ed)?|make\\s+(?:it\\s+)?(?:work|compatible)|new\\s+versions?)(?:\\s+(?:this|it|the\\s+mod|to|for|with))?|(?:update|port|support)(?:d)?\\s+(?:this|it|the\\s+mod|for|to)(?:\\s+(?:ver(?:sion)?|spt|latest|new|newer|\\d+\\.\\d+(?:\\.\\d+)?(?:\\.\\w+)?))?|(?:work|working|compatible)(?:ing)?\\s+(?:with|on|for)(?:\\s+(?:older\\s+)?(?:ver(?:sion)?(?:\\s+of)?|spt|latest|new|newer|\\d+\\.\\d+(?:\\.\\d+)?(?:\\.\\w+)?))?|waiting\\s+for\\s+(?:update|port)|(?:still|not)\\s+(?:working|updated|supported)', 'i');
            this.$watch('content', () => {
                this.checkForLogFile();
                this.checkForUpdateRequest();
            });
        },
        checkForLogFile() {
            const hasLogFile = this.logFilePattern.test(this.content || '');
            if (this.containsLogFile !== hasLogFile) {
                this.containsLogFile = hasLogFile;
                this.$dispatch('log-file-detected', { containsLogFile: hasLogFile });
            }
        },
        checkForUpdateRequest() {
            const hasUpdateRequest = this.updateRequestPattern.test(this.content || '');
            if (this.containsUpdateRequest !== hasUpdateRequest) {
                this.containsUpdateRequest = hasUpdateRequest;
                this.$dispatch('update-request-detected', { containsUpdateRequest: hasUpdateRequest });
            }
        },
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

    {{-- Log File Detection Warning --}}
    <div
        x-show="containsLogFile"
        x-cloak
    >
        <flux:callout
            variant="danger"
            icon="x-circle"
        >
            <flux:callout.heading>{{ __('Log files detected!') }}</flux:callout.heading>
            <flux:callout.text>
                Please use our code paste service instead:
                <flux:callout.link
                    href="https://codepaste.sp-tarkov.com"
                    external
                >https://codepaste.sp-tarkov.com</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    </div>

    {{-- Update Request Warning --}}
    <div
        x-show="containsUpdateRequest"
        x-cloak
    >
        <flux:callout
            variant="warning"
            icon="exclamation-triangle"
        >
            <flux:callout.heading>{{ __('Warning: Potential Update Request Detected') }}</flux:callout.heading>
            <flux:callout.text>
                Pestering or harassing mod authors to update their mods is against our <flux:callout.link
                    href="/community-standards"
                    external
                >community guidelines</flux:callout.link>. First offense is a 7-day ban. Please be respectful and
                patient with mod authors.
            </flux:callout.text>
        </flux:callout>
    </div>

    <flux:error name="{{ $errorName ?? $name }}" />
</div>
