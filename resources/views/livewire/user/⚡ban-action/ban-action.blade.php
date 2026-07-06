<div>
    @if ($user->isBanned())
        <flux:button
            x-on:click="$wire.showUnbanModal = true"
            variant="outline"
            size="{{ $size }}"
            class="whitespace-nowrap"
        >
            <div class="flex items-center">
                <flux:icon.shield-check class="{{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5 text-green-600" />
                {{ __('Unban User') }}
            </div>
        </flux:button>
    @else
        <flux:button
            x-on:click="$wire.showBanModal = true"
            variant="outline"
            size="{{ $size }}"
            class="whitespace-nowrap"
        >
            <div class="flex items-center">
                <flux:icon.shield-exclamation class="{{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5 text-red-500" />
                {{ __('Ban User') }}
            </div>
        </flux:button>
    @endif

    {{-- Ban Modal --}}
    <flux:modal
        name="ban-modal"
        wire:model.self="showBanModal"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="shield-exclamation"
                        class="h-8 w-8 text-red-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Ban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Restrict user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-6">
                <div class="rounded-lg border border-red-800 bg-red-900/20 p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon
                            name="exclamation-triangle"
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500"
                        />
                        <div>
                            <flux:text class="text-sm font-medium text-red-200">
                                {{ __('Warning') }}
                            </flux:text>
                            <flux:text class="mt-1 text-sm text-red-300">
                                {{ __('Banned users cannot access the platform when logged in, but may still access content when logged out.') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                <div>
                    <flux:radio.group
                        wire:model.live="duration"
                        label="{{ __('Ban Duration') }}"
                        class="text-left"
                    >
                        @foreach ($this->getDurationOptions() as $value => $label)
                            <flux:radio
                                value="{{ $value }}"
                                label="{{ $label }}"
                            />
                        @endforeach
                    </flux:radio.group>
                </div>

                <div>
                    <flux:textarea
                        wire:model.live="reason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Please provide a reason for this ban...') }}"
                        rows="3"
                    />
                    <p class="mt-1 text-xs text-gray-400">
                        {{ __('This reason will be visible to the banned user.') }}
                    </p>
                </div>

                @if ($this->availableReports->isNotEmpty())
                    <div>
                        <flux:select
                            variant="listbox"
                            wire:model="selectedReportId"
                            label="{{ __('Link to Report (optional)') }}"
                        >
                            <flux:select.option value="0">{{ __('No report') }}</flux:select.option>
                            @foreach ($this->availableReports as $report)
                                <flux:select.option value="{{ $report->id }}">
                                    #{{ $report->id }} - {{ $report->reason->label() }} -
                                    {{ $report->reporter->name }} - {{ $report->created_at->diffForHumans() }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ __('Selecting a report will automatically resolve it after banning.') }}
                        </p>
                    </div>
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex items-center justify-between border-t border-gray-700 pt-6">
                <div class="flex items-center text-xs text-gray-400">
                    <flux:icon
                        name="information-circle"
                        class="mr-2 h-4 w-4 flex-shrink-0"
                    />
                    <span class="leading-tight">
                        {{ __('This action can be reversed by unbanning the user') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button
                        x-on:click="$wire.showBanModal = false"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="ban"
                        variant="danger"
                        size="sm"
                        icon="shield-exclamation"
                    >
                        {{ __('Ban User') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- Unban Modal --}}
    <flux:modal
        name="unban-modal"
        wire:model.self="showUnbanModal"
        class="md:w-[400px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="shield-check"
                        class="h-8 w-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Unban User') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Restore user access to the platform') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-300">
                    {{ __('Are you sure you want to unban this user? They will regain full access to the platform.') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    x-on:click="$wire.showUnbanModal = false"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="unban"
                    variant="primary"
                    size="sm"
                    icon="shield-check"
                >
                    {{ __('Unban User') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
