<x-slot:title>
    {{ __('Chat - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Connect and chat with other members of The Forge community.') }}
</x-slot>

<x-slot:header>
    <div class="flex items-center justify-between w-full">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
            {{ __('Chat') }}
        </h2>
    </div>
</x-slot>

<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
    <div class="px-4 py-8 sm:px-6 lg:px-8 bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
        {{-- Chat Interface --}}
        <div class="flex h-[calc(100vh-12rem)] bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-sm">
            {{-- Left Column: Conversations List --}}
            <div class="w-80 border-r border-gray-200 dark:border-gray-700 flex flex-col bg-gray-50 dark:bg-gray-800">
                {{-- Header with New Conversation Button --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                    <flux:button variant="primary" class="w-full" wire:click="$set('showNewConversation', true)">
                        <div class="flex items-center justify-center gap-2">
                            <flux:icon.plus class="h-4 w-4" />
                            <span>{{ __('New Conversation') }}</span>
                        </div>
                    </flux:button>
                </div>

                {{-- Conversations List (Hard-coded examples) --}}
                <div class="flex-1 overflow-y-auto">
                    {{-- Conversation 1 - Active/Selected --}}
                    <button class="w-full flex items-center gap-3 p-4 bg-gray-200 dark:bg-gray-700 border-l-4 border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900 transition-all duration-200 shadow-sm">
                        <flux:avatar badge badge:circle badge:color="green" src="https://ui-avatars.com/api/?name=John+Doe" size="md" />
                        <div class="flex-1 text-left">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-gray-900 dark:text-gray-100">John Doe</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">2m ago</div>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                Hey, how's the project going?
                            </div>
                        </div>
                    </button>

                    {{-- Conversation 2 - With unread badge --}}
                    <button class="w-full flex items-center gap-3 p-4 hover:bg-blue-50 dark:hover:bg-blue-900 hover:shadow-sm transition-all duration-200">
                        <flux:avatar src="https://ui-avatars.com/api/?name=Jane+Smith" size="md" />
                        <div class="flex-1 text-left">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">Jane Smith</div>
                                    <flux:badge color="red" size="sm">3</flux:badge>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">1h ago</div>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                Can you review my latest PR?
                            </div>
                        </div>
                    </button>

                    {{-- Conversation 3 --}}
                    <button class="w-full flex items-center gap-3 p-4 hover:bg-blue-50 dark:hover:bg-blue-900 hover:shadow-sm transition-all duration-200">
                        <flux:avatar src="https://ui-avatars.com/api/?name=Mike+Johnson" size="md" />
                        <div class="flex-1 text-left">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-gray-900 dark:text-gray-100">Mike Johnson</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Yesterday</div>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                Thanks for the help!
                            </div>
                        </div>
                    </button>

                    {{-- Conversation 4 - Online --}}
                    <button class="w-full flex items-center gap-3 p-4 hover:bg-blue-50 dark:hover:bg-blue-900 hover:shadow-sm transition-all duration-200">
                        <flux:avatar badge badge:circle badge:color="green" src="https://ui-avatars.com/api/?name=Sarah+Wilson" size="md" />
                        <div class="flex-1 text-left">
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-gray-900 dark:text-gray-100">Sarah Wilson</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">2 days ago</div>
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                Let's schedule a meeting
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            {{-- Right Column: Selected Conversation --}}
            <div class="flex-1 flex flex-col bg-white dark:bg-gray-900">
                {{-- Conversation Header --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-center gap-3">
                        <flux:avatar src="https://ui-avatars.com/api/?name=John+Doe" size="sm" />
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">John Doe</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="text-green-500">{{ __('Online') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Conversation Actions --}}
                    <flux:dropdown align="end">
                        <flux:button variant="ghost" size="sm" square>
                            <flux:icon.ellipsis-vertical class="h-5 w-5" />
                        </flux:button>
                        <flux:menu>
                            <flux:menu.item icon="user">{{ __('View Profile') }}</flux:menu.item>
                            <flux:menu.item icon="bell-slash">{{ __('Mute Notifications') }}</flux:menu.item>
                            <flux:separator />
                            <flux:menu.item icon="trash" variant="danger">{{ __('Delete Conversation') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                {{-- Messages Area --}}
                <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gradient-to-b from-gray-50/50 to-white dark:from-gray-900/50 dark:to-gray-900" id="messages-container">
                    {{-- Message from other user --}}
                    <div class="flex justify-start">
                        <div class="flex gap-3 max-w-[70%]">
                            <flux:avatar src="https://ui-avatars.com/api/?name=John+Doe" size="xs" class="flex-shrink-0" />
                            <div class="flex flex-col items-start">
                                <div class="px-4 py-2 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm">
                                    <p class="text-sm">Hi! How are you doing today?</p>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 px-2">10:30 AM</div>
                            </div>
                        </div>
                    </div>

                    {{-- Message from current user --}}
                    <div class="flex justify-end">
                        <div class="flex gap-3 max-w-[70%] flex-row-reverse">
                            <flux:avatar src="https://ui-avatars.com/api/?name=You" size="xs" class="flex-shrink-0" />
                            <div class="flex flex-col items-end">
                                <div class="px-4 py-2 rounded-2xl bg-blue-500 text-white shadow-sm">
                                    <p class="text-sm">I'm doing great, thanks! Just working on the new feature.</p>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 px-2">
                                    10:32 AM
                                    <flux:icon.check class="inline h-3 w-3 ml-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Message from other user --}}
                    <div class="flex justify-start">
                        <div class="flex gap-3 max-w-[70%]">
                            <flux:avatar src="https://ui-avatars.com/api/?name=John+Doe" size="xs" class="flex-shrink-0" />
                            <div class="flex flex-col items-start">
                                <div class="px-4 py-2 rounded-2xl bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm">
                                    <p class="text-sm">That sounds awesome! How's the project going? Are you still on track for the deadline?</p>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 px-2">10:33 AM</div>
                            </div>
                        </div>
                    </div>

                    {{-- Message from current user --}}
                    <div class="flex justify-end">
                        <div class="flex gap-3 max-w-[70%] flex-row-reverse">
                            <flux:avatar src="https://ui-avatars.com/api/?name=You" size="xs" class="flex-shrink-0" />
                            <div class="flex flex-col items-end">
                                <div class="px-4 py-2 rounded-2xl bg-blue-500 text-white shadow-sm">
                                    <p class="text-sm">Yes, everything is on schedule. I should have it ready for review by tomorrow.</p>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 px-2">
                                    10:35 AM
                                    <flux:icon.check class="inline h-3 w-3 ml-1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Typing Indicator --}}
                    <div class="flex justify-start">
                        <div class="flex gap-3 max-w-[70%]">
                            <flux:avatar src="https://ui-avatars.com/api/?name=John+Doe" size="xs" class="flex-shrink-0" />
                            <div class="px-4 py-3 bg-gray-100 dark:bg-gray-800 rounded-2xl shadow-sm">
                                <div class="flex items-center gap-1">
                                    <div class="typing-dot w-2 h-2 bg-gray-500 dark:bg-gray-400 rounded-full"></div>
                                    <div class="typing-dot w-2 h-2 bg-gray-500 dark:bg-gray-400 rounded-full"></div>
                                    <div class="typing-dot w-2 h-2 bg-gray-500 dark:bg-gray-400 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <style>
                        .typing-dot {
                            animation: typing 1.4s infinite ease-in-out;
                        }
                        
                        .typing-dot:nth-child(1) {
                            animation-delay: 0ms;
                        }
                        
                        .typing-dot:nth-child(2) {
                            animation-delay: 200ms;
                        }
                        
                        .typing-dot:nth-child(3) {
                            animation-delay: 400ms;
                        }
                        
                        @keyframes typing {
                            0%, 60%, 100% {
                                transform: translateY(0);
                                opacity: 0.4;
                            }
                            30% {
                                transform: translateY(-8px);
                                opacity: 1;
                            }
                        }
                    </style>
                </div>

                {{-- Message Input Area --}}
                <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div x-data="{ 
                        messageText: '', 
                        maxLength: 500,
                        get remaining() { return this.maxLength - this.messageText.length; },
                        get showCounter() { return this.messageText.length >= 400; },
                        get isOverLimit() { return this.messageText.length > this.maxLength; },
                        resize() { 
                            const textarea = $refs.textarea; 
                            textarea.style.height = 'auto'; 
                            const newHeight = Math.min(Math.max(textarea.scrollHeight, 46), 120); 
                            textarea.style.height = newHeight + 'px'; 
                            const button = $refs.button; 
                            if (button) { button.style.height = newHeight + 'px'; } 
                        } 
                    }">
                        <form class="flex gap-2">
                            <flux:textarea
                                placeholder="{{ __('Type a message...') }}"
                                rows="1"
                                class="flex-1 resize-none min-h-[46px]"
                                maxlength="500"
                                x-ref="textarea"
                                x-model="messageText"
                                x-init="resize()"
                                @input="resize()"
                            />
                            <flux:button type="submit" variant="primary" square class="shadow-sm hover:shadow-md transition-shadow duration-200 h-[46px]" x-ref="button">
                                <flux:icon.paper-airplane class="size-5" />
                            </flux:button>
                        </form>
                        <div x-show="showCounter" 
                             x-text="remaining + ' characters remaining'"
                             class="text-xs text-right mt-2"
                             :class="isOverLimit ? 'text-red-500' : remaining < 50 ? 'text-amber-500' : 'text-gray-500 dark:text-gray-400'"
                             x-transition>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- New Conversation Modal --}}
    <flux:modal wire:model.live="showNewConversation" class="md:w-[500px] lg:w-[600px]">
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="chat-bubble-left-right" class="w-8 h-8 text-blue-600" />
                    <div>
                        <flux:heading size="xl" class="text-gray-900 dark:text-gray-100">
                            {{ __('Start New Conversation') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Search for users to start chatting with') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:input
                    placeholder="{{ __('Search for users...') }}"
                    icon="magnifying-glass"
                />

                {{-- Search Results (Hard-coded examples) --}}
                <div class="max-h-64 overflow-y-auto space-y-1">
                    <button class="w-full flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center gap-3">
                            <flux:avatar src="https://ui-avatars.com/api/?name=Alice+Brown" size="sm" />
                            <div class="font-medium text-gray-900 dark:text-gray-100">Alice Brown</div>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">23 mods</div>
                    </button>

                    <button class="w-full flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center gap-3">
                            <flux:avatar src="https://ui-avatars.com/api/?name=Bob+Davis" size="sm" />
                            <div class="font-medium text-gray-900 dark:text-gray-100">Bob Davis</div>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">8 mods</div>
                    </button>

                    <button class="w-full flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center gap-3">
                            <flux:avatar src="https://ui-avatars.com/api/?name=Carol+Martinez" size="sm" />
                            <div class="font-medium text-gray-900 dark:text-gray-100">Carol Martinez</div>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">15 mods</div>
                    </button>
                    
                    <button class="w-full flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center gap-3">
                            <flux:avatar src="https://ui-avatars.com/api/?name=David+Wilson" size="sm" />
                            <div class="font-medium text-gray-900 dark:text-gray-100">David Wilson</div>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">42 mods</div>
                    </button>
                    
                    <button class="w-full flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center gap-3">
                            <flux:avatar src="https://ui-avatars.com/api/?name=Emily+Johnson" size="sm" />
                            <div class="font-medium text-gray-900 dark:text-gray-100">Emily Johnson</div>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">7 mods</div>
                    </button>
                    
                    <button class="w-full flex items-center justify-between gap-3 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="flex items-center gap-3">
                            <flux:avatar src="https://ui-avatars.com/api/?name=Frank+Anderson" size="sm" />
                            <div class="font-medium text-gray-900 dark:text-gray-100">Frank Anderson</div>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">31 mods</div>
                    </button>
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-between items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center text-xs text-gray-500 dark:text-gray-400">
                    <flux:icon name="shield-check" class="w-4 h-4 mr-2 flex-shrink-0" />
                    <span class="leading-tight">
                        {{ __('1-on-1 conversations only') }}
                    </span>
                </div>

                <div class="flex gap-3">
                    <flux:button wire:click="$set('showNewConversation', false)" variant="outline" size="sm">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" size="sm" icon="chat-bubble-left-right">
                        {{ __('Start Chat') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:modal>
</div>
