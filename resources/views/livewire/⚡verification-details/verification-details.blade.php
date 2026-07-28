@placeholder
    <div class="space-y-4 py-2">
        <flux:skeleton.group class="space-y-4">
            <flux:skeleton class="h-6 w-40 rounded" />
            <flux:skeleton class="h-4 w-full rounded" />
            <flux:skeleton class="h-4 w-3/4 rounded" />
            <flux:skeleton class="h-48 w-full rounded" />
        </flux:skeleton.group>
    </div>
@endplaceholder

<div class="space-y-6">
    @if ($this->displayResult)
        <div>
            <flux:heading size="lg">{{ __('File Verification') }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge
                    :color="$this->displayResult->status->color()"
                    :icon="$this->displayResult->status->icon()"
                    size="sm"
                >
                    {{ $this->displayResult->status->label() }}
                </flux:badge>
                @if ($this->displayResult->completed_at)
                    <span class="text-sm text-gray-400">
                        {{ __('Verified') }} {{ $this->displayResult->completed_at->dynamicFormat() }}
                    </span>
                @endif
                @if ($this->canSubmit && !$this->activeResult)
                    <flux:button
                        wire:click="submit"
                        size="sm"
                        variant="outline"
                        icon="arrow-path"
                        data-test="verification-submit-button"
                        class="ml-auto"
                    >
                        {{ __('Resubmit Verification') }}
                    </flux:button>
                @endif
            </div>
        </div>

        @if ($this->activeResult)
            <flux:callout
                icon="arrow-path"
                color="blue"
                data-test="verification-rerun-banner"
            >
                <flux:callout.text>
                    @if ($this->queuePosition !== null)
                        {{ __('A new verification run is queued at position :position. This panel updates automatically.', ['position' => Number::format($this->queuePosition)]) }}
                    @else
                        {{ __('A new verification run is in progress. This panel updates automatically.') }}
                    @endif
                </flux:callout.text>
            </flux:callout>
        @endif

        <div>
            <span class="text-sm text-gray-400">{{ __('Download URL') }}</span>
            <p class="mt-1 select-none break-all text-sm text-gray-100">
                {{ $this->displayResult->download_url }}
            </p>
        </div>

        @if ($this->formattedFileSize)
            <div>
                <span class="text-sm text-gray-400">{{ __('File Size') }}</span>
                <p class="mt-1 text-sm text-gray-100">
                    {{ $this->formattedFileSize }}
                </p>
            </div>
        @endif

        @if ($this->displayResult->downloaded_sha256)
            <flux:input
                :value="$this->displayResult->downloaded_sha256"
                label="{{ __('Archive SHA-256') }}"
                data-test="verification-sha256"
                readonly
                copyable
                class="font-mono"
            />
        @endif

        <x-verification.check-list :checks="$this->checks" />

        @if ($this->fileCount > 0)
            <div>
                <span class="text-sm text-gray-400">
                    {{ __('Archive Contents') }}
                    ({{ Number::format($this->fileCount) }} {{ __(Str::plural('file', $this->fileCount)) }})
                </span>
                <x-verification.file-tree
                    :nodes="$this->fileTree"
                    class="mt-2"
                />
                @if ($this->hiddenFileCount > 0)
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __(':count more files not shown', ['count' => Number::format($this->hiddenFileCount)]) }}
                    </p>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-400">{{ __('No file listing available.') }}</p>
        @endif
    @elseif ($this->activeResult)
        <div>
            <flux:heading size="lg">{{ __('File Verification') }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @if ($this->activeResult->status === \App\Enums\VerificationStatus::Running)
                    <flux:badge
                        color="blue"
                        size="sm"
                    >
                        <flux:icon.loading class="mr-1 h-4 w-4" />
                        {{ $this->activeResult->status->label() }}
                    </flux:badge>
                @else
                    <flux:badge
                        :color="$this->activeResult->status->color()"
                        :icon="$this->activeResult->status->icon()"
                        size="sm"
                    >
                        {{ $this->activeResult->status->label() }}
                    </flux:badge>
                @endif
            </div>
        </div>

        <div
            data-test="verification-progress"
            class="space-y-4"
        >
            <div>
                <span class="text-sm text-gray-400">{{ __('Trigger') }}</span>
                <p class="mt-1 text-sm text-gray-100">
                    {{ $this->activeResult->trigger->label() }}
                </p>
            </div>

            <div>
                <span class="text-sm text-gray-400">{{ __('Requested') }}</span>
                <p class="mt-1 text-sm text-gray-100">
                    {{ $this->activeResult->created_at?->dynamicFormat() }}
                </p>
            </div>

            @if ($this->activeResult->started_at)
                <div>
                    <span class="text-sm text-gray-400">{{ __('Started') }}</span>
                    <p class="mt-1 text-sm text-gray-100">
                        {{ $this->activeResult->started_at->dynamicFormat() }}
                    </p>
                </div>
            @endif

            @if ($this->queuePosition !== null)
                <div>
                    <span class="text-sm text-gray-400">{{ __('Position in queue') }}</span>
                    <p class="mt-1 text-sm text-gray-100">
                        {{ Number::format($this->queuePosition) }}
                    </p>
                </div>
            @endif

            <p class="text-sm text-gray-400">{{ __('This panel updates automatically.') }}</p>
        </div>
    @else
        <p class="text-sm text-gray-400">{{ __('Verification details are currently unavailable.') }}</p>

        @if ($this->canSubmit)
            <flux:button
                wire:click="submit"
                size="sm"
                variant="outline"
                icon="arrow-path"
                data-test="verification-submit-button"
            >
                {{ __('Submit for Verification') }}
            </flux:button>
        @endif
    @endif
</div>
