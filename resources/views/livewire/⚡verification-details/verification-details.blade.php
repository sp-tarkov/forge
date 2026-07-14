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
    @if ($this->result)
        <div>
            <flux:heading size="lg">{{ __('File Verification') }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge
                    :color="$this->result->status->color()"
                    :icon="$this->result->status->icon()"
                    size="sm"
                >
                    {{ $this->result->status->label() }}
                </flux:badge>
                @if ($this->result->completed_at)
                    <span class="text-sm text-gray-400">
                        {{ __('Verified') }} {{ $this->result->completed_at->dynamicFormat() }}
                    </span>
                @endif
            </div>
        </div>

        <div>
            <span class="text-sm text-gray-400">{{ __('Download URL') }}</span>
            <p class="mt-1 select-none break-all text-sm text-gray-100">
                {{ $this->result->download_url }}
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

        @if ($this->result->downloaded_sha256)
            <flux:input
                :value="$this->result->downloaded_sha256"
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
    @else
        <p class="text-sm text-gray-400">{{ __('Verification details are currently unavailable.') }}</p>
    @endif
</div>
