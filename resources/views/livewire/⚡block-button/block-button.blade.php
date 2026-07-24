<div>
    @auth
        @if (auth()->user()->id !== $user->id)
            <form>
                <flux:button
                    wire:click="toggleBlockModal"
                    variant="outline"
                    size="{{ $size }}"
                    class="whitespace-nowrap"
                >
                    <div class="flex items-center">
                        <flux:icon.no-symbol
                            variant="{{ $isBlocked ? 'solid' : 'outline' }}"
                            class="{{ $isBlocked ? 'text-red-500' : 'text-white' }} {{ $size === 'xs' ? 'size-3' : 'size-4' }} mr-1.5"
                        />
                        {{ $isBlocked ? __('Blocked') : __('Block User') }}
                    </div>
                </flux:button>
            </form>

            <!-- Block/Unblock Confirmation Modal -->
            <flux:modal
                wire:model.live="showModal"
                class="md:w-[500px] lg:w-[600px]"
            >
                <div class="space-y-0">
                    {{-- Header Section --}}
                    <div class="mb-6 border-b border-gray-700 pb-6">
                        <div class="flex items-center gap-3">
                            <flux:icon
                                name="{{ $isBlocked ? 'x-mark' : 'no-symbol' }}"
                                class="{{ $isBlocked ? 'text-green-600' : 'text-red-600' }} h-8 w-8"
                            />
                            <div>
                                <flux:heading
                                    size="xl"
                                    class="text-gray-100"
                                >
                                    {{ $isBlocked ? __('Unblock User') : __('Block User') }}
                                </flux:heading>
                                <flux:text class="mt-1 text-sm text-gray-400">
                                    {{ $user->name }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    {{-- Content Section --}}
                    <div class="space-y-4">
                        @if ($isBlocked)
                            <flux:text class="text-sm text-gray-300">
                                {{ __('Are you sure you want to unblock this user? You will be able to see their content and they will be able to interact with you again.') }}
                            </flux:text>
                        @else
                            <div class="space-y-2">
                                <flux:text class="text-sm font-medium text-gray-100">
                                    {{ __('What happens when you block someone:') }}
                                </flux:text>
                                <ul class="ml-2 list-inside list-disc space-y-1 text-sm text-gray-300">
                                    <li>{{ __('Their comments will be collapsed and hidden from you') }}</li>
                                    <li>{{ __('They can\'t send you messages or comment on your content') }}</li>
                                    <li>{{ __('They won\'t be able to view your profile') }}</li>
                                    <li>{{ __('You both will be unfollowed from each other') }}</li>
                                    <li>{{ __('You\'ll no longer receive notifications from them') }}</li>
                                </ul>
                            </div>

                            <flux:text class="text-sm text-gray-300">
                                {{ __('You can optionally provide a reason for blocking (only visible to you in your blocked users list):') }}
                            </flux:text>

                            <flux:textarea
                                wire:model="blockReason"
                                placeholder="{{ __('Optional: Reason for blocking...') }}"
                                rows="3"
                                class="w-full"
                            />
                        @endif
                    </div>

                    {{-- Footer Section --}}
                    <div class="mt-6 flex justify-end gap-3 border-t border-gray-700 pt-6">
                        <flux:button
                            wire:click="toggleBlockModal"
                            variant="outline"
                        >
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            wire:click="confirmBlock"
                            variant="{{ $isBlocked ? 'primary' : 'danger' }}"
                            wire:loading.attr="disabled"
                        >
                            <div class="flex items-center">
                                <flux:icon
                                    name="{{ $isBlocked ? 'check' : 'no-symbol' }}"
                                    variant="micro"
                                    class="mr-1.5"
                                    wire:loading.remove
                                />
                                <flux:icon
                                    name="arrow-path"
                                    variant="micro"
                                    class="mr-1.5 animate-spin"
                                    wire:loading
                                />
                                {{ $isBlocked ? __('Unblock User') : __('Block User') }}
                            </div>
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    @endauth
</div>
