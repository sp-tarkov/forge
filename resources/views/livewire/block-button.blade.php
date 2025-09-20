<div>
    @auth
        @if(auth()->user()->id !== $user->id)
            <form>
                <flux:button
                    wire:click="toggleBlockModal"
                    variant="outline"
                    size="sm"
                    class="whitespace-nowrap">
                    <div class="flex items-center">
                        <flux:icon.no-symbol variant="{{ $isBlocked ? 'solid' : 'outline' }}" class="{{ $isBlocked ? 'text-red-500' : 'text-white' }} mr-1.5" />
                        {{ $isBlocked ? __('Blocked') : __('Block User') }}
                    </div>
                </flux:button>
            </form>

            <!-- Block/Unblock Confirmation Modal -->
            <flux:modal wire:model.live="showModal" class="md:w-[500px] lg:w-[600px]">
                <div class="space-y-0">
                    {{-- Header Section --}}
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                        <div class="flex items-center gap-3">
                            <flux:icon name="{{ $isBlocked ? 'x-mark' : 'no-symbol' }}" class="w-8 h-8 {{ $isBlocked ? 'text-green-600' : 'text-red-600' }}" />
                            <div>
                                <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                                    {{ $isBlocked ? __('Unblock User') : __('Block User') }}
                                </flux:heading>
                                <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                                    {{ $user->name }}
                                </flux:text>
                            </div>
                        </div>
                    </div>

                    {{-- Content Section --}}
                    <div class="space-y-4">
                        @if($isBlocked)
                            <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                                {{ __('Are you sure you want to unblock this user? You will be able to see their content and they will be able to interact with you again.') }}
                            </flux:text>
                        @else
                            <div class="space-y-2">
                                <flux:text class="text-gray-900 dark:text-gray-100 text-sm font-medium">
                                    {{ __('What happens when you block someone:') }}
                                </flux:text>
                                <ul class="text-gray-700 dark:text-gray-300 text-sm list-disc list-inside space-y-1 ml-2">
                                    <li>{{ __('You won\'t see their comments or messages') }}</li>
                                    <li>{{ __('They can\'t send you messages or comment on your content') }}</li>
                                    <li>{{ __('Any existing conversations will be archived') }}</li>
                                    <li>{{ __('You both will be unfollowed from each other') }}</li>
                                </ul>
                            </div>

                            <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                                {{ __('You can optionally provide a reason for blocking (only visible to moderators):') }}
                            </flux:text>

                            <flux:textarea
                                wire:model="blockReason"
                                placeholder="{{ __('Optional: Reason for blocking...') }}"
                                rows="3"
                                class="w-full" />
                        @endif
                    </div>

                    {{-- Footer Section --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6 flex justify-end gap-3">
                        <flux:button wire:click="toggleBlockModal" variant="outline">
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button
                            wire:click="confirmBlock"
                            variant="{{ $isBlocked ? 'primary' : 'danger' }}"
                            wire:loading.attr="disabled">
                            <div class="flex items-center">
                                <flux:icon name="{{ $isBlocked ? 'check' : 'no-symbol' }}" variant="micro" class="mr-1.5" wire:loading.remove />
                                <flux:icon name="arrow-path" variant="micro" class="mr-1.5 animate-spin" wire:loading />
                                {{ $isBlocked ? __('Unblock User') : __('Block User') }}
                            </div>
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif
    @endauth
</div>