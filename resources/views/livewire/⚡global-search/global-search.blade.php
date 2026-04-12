<div class="flex flex-1 justify-center px-2 lg:ml-6 lg:justify-end">
    <div class="w-full max-w-lg lg:max-w-xs">
        {{-- Trigger Button --}}
        <flux:modal.trigger name="global-search" shortcut="cmd.k">
            <flux:input
                as="button"
                placeholder="{{ __('Search...') }}"
                icon="magnifying-glass"
                kbd="⌘K"
            />
        </flux:modal.trigger>
    </div>

    {{-- Search Modal --}}
    <flux:modal
        name="global-search"
        variant="bare"
        class="w-full max-w-[36rem] my-[12vh] max-h-screen overflow-y-hidden"
    >
        <flux:command class="border-none shadow-lg inline-flex flex-col max-h-[76vh]">
            <flux:command.input
                id="global-search"
                wire:model.live.debounce.250ms="query"
                placeholder="{{ __('Search everything...') }}"
                closable
            />

            @if (Str::length($this->query) > 0)
                <flux:command.items class="max-h-[60vh] overflow-y-auto">
                    {{-- Loading State --}}
                    <div
                        wire:loading.delay
                        wire:target="query"
                        class="flex items-center justify-center gap-2 px-6 py-14"
                    >
                        <flux:icon.arrow-path class="size-5 animate-spin text-gray-400 dark:text-gray-500" />
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('Searching...') }}</span>
                    </div>

                    {{-- Results Content --}}
                    <div
                        wire:loading.delay.remove
                        wire:target="query"
                    >
                        @if ($this->hasResults)
                            @foreach ($this->results as $type => $typeResults)
                                @if ($typeResults->count())
                                    @php
                                        $visibilityProperty = 'is' . Str::ucfirst($type) . 'CatVisible';
                                        $isVisible = $this->$visibilityProperty;
                                    @endphp
                                    <h4
                                        wire:click="toggleTypeVisibility('{{ $type }}')"
                                        class="flex cursor-pointer select-none flex-row gap-1.5 bg-gray-100 px-4 py-2.5 text-[0.6875rem] font-semibold uppercase text-gray-700 dark:bg-gray-950 dark:text-gray-300"
                                    >
                                        <span>{{ Str::plural($type) }}</span>
                                        <flux:icon.chevron-right
                                            class="size-4 transform transition-all duration-400 {{ $isVisible ? 'rotate-90' : '' }}"
                                        />
                                    </h4>
                                    <div
                                        class="max-h-0 transform divide-y divide-dashed divide-gray-200 overflow-hidden transition-all duration-400 dark:divide-gray-800 {{ $isVisible ? 'max-h-screen' : '' }}">
                                        @foreach ($typeResults as $hit)
                                            <x-dynamic-component
                                                :component="'global-search-result-' . Str::lower($type)"
                                                :result="$hit"
                                                link-class="group/global-search-link flex flex-row gap-3 py-1.5 px-4 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-800"
                                            />
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        @else
                            {{-- No Results --}}
                            <div class="px-6 py-14 text-center sm:px-14">
                                <flux:icon.document-magnifying-glass
                                    class="mx-auto size-6 text-gray-400 dark:text-gray-500" />
                                <p class="mt-4 text-sm text-gray-900 dark:text-gray-200">
                                    {{ __("We couldn't find any content with that query. Please try again.") }}
                                </p>
                            </div>
                        @endif
                    </div>
                </flux:command.items>
            @endif
        </flux:command>
    </flux:modal>
</div>
