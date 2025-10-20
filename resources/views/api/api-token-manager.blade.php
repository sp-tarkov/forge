<x-slot:title>
    {{ __('Manage API Tokens - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Manage your API tokens to access our API endpoints.') }}
</x-slot>

<div>
    <!-- Generate API Token -->
    <x-form-section submit="createApiToken">
        <x-slot name="title">
            {{ __('Create API Token') }}
        </x-slot>

        <x-slot name="description">
            {{ __('API tokens allow third-party services to authenticate with our application on your behalf.') }}<br /><br />
            {!! __(
                'Please read the <a href="/docs/index.html" class="underline text-gray-800 hover:text-black dark:text-gray-200 dark:hover:text-white">API documentation</a> for examples and details on the available API endpoints. Build something great! :D',
            ) !!}
        </x-slot>

        <x-slot name="form">
            <!-- Token Name -->
            <div class="col-span-6 sm:col-span-4">
                <x-label
                    for="name"
                    value="{{ __('Token Name') }}"
                />
                <x-input
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    wire:model="createApiTokenForm.name"
                    autofocus
                />
                <x-input-error
                    for="name"
                    class="mt-2"
                />
            </div>

            <!-- Token Permissions -->
            @if (Laravel\Jetstream\Jetstream::hasPermissions())
                <div class="col-span-6">
                    <x-label
                        for="permissions"
                        value="{{ __('Permissions') }}"
                    />

                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                            <label class="flex items-center">
                                <x-checkbox
                                    wire:model="createApiTokenForm.permissions"
                                    :value="$permission"
                                />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ $permission }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-slot>

        <x-slot name="actions">
            <x-action-message
                class="me-3"
                on="created"
            >
                {{ __('Created.') }}
            </x-action-message>

            <x-button>
                {{ __('Create') }}
            </x-button>
        </x-slot>
    </x-form-section>

    @if ($this->user->tokens->isNotEmpty())
        <x-section-border />

        <!-- Manage API Tokens -->
        <div class="mt-10 sm:mt-0">
            <x-action-section>
                <x-slot name="title">
                    {{ __('Manage API Tokens') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('You may delete any of your existing tokens if they are no longer needed.') }}
                </x-slot>

                <!-- API Token List -->
                <x-slot name="content">
                    <div class="space-y-6">
                        @foreach ($this->user->tokens->sortBy('name') as $token)
                            <div class="flex items-center justify-between">
                                <div class="break-all text-gray-900 dark:text-gray-100">
                                    {{ $token->name }}
                                </div>

                                <div class="flex items-center ms-2">
                                    @if ($token->last_used_at)
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                        </div>
                                    @endif

                                    @if (Laravel\Jetstream\Jetstream::hasPermissions())
                                        <button
                                            class="cursor-pointer ms-6 text-sm text-gray-700 dark:text-gray-300 underline"
                                            wire:click="manageApiTokenPermissions({{ $token->id }})"
                                        >
                                            {{ __('Permissions') }}
                                        </button>
                                    @endif

                                    <button
                                        class="cursor-pointer ms-6 text-sm underline text-red-500"
                                        wire:click="confirmApiTokenDeletion({{ $token->id }})"
                                    >
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-slot>
            </x-action-section>
        </div>
    @endif

    <!-- Token Value Modal -->
    <flux:modal
        wire:model.live="displayingToken"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="key"
                        class="w-8 h-8 text-blue-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('API Token') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Copy and save this token securely') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <div class="bg-blue-50 dark:bg-blue-950/30 border border-blue-300 dark:border-blue-700 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon
                            name="information-circle"
                            class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0"
                        />
                        <div>
                            <flux:text class="text-blue-900 dark:text-blue-200 text-sm font-medium">
                                {{ __('Important!') }}
                            </flux:text>
                            <flux:text class="text-blue-800 dark:text-blue-300 text-sm mt-1">
                                {{ __('Please copy and securely store your new API token now. For your security, it will not be shown to you again.') }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                <flux:input
                    x-ref="plaintextToken"
                    type="text"
                    readonly
                    :value="$plainTextToken"
                    class="font-mono text-sm w-full break-all"
                    style="text-align: center !important; padding-left: 0 !important; padding-right: 0 !important;"
                    autofocus
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    @showing-token-modal.window="setTimeout(() => $refs.plaintextToken.select(), 250)"
                />
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    wire:click="$set('displayingToken', false)"
                    wire:loading.attr="disabled"
                    variant="primary"
                    size="sm"
                >
                    {{ __('Close') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- API Token Permissions Modal -->
    <flux:modal
        wire:model.live="managingApiTokenPermissions"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="shield-check"
                        class="w-8 h-8 text-green-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('API Token Permissions') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('Configure what this token can access') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                        <label class="flex items-center">
                            <flux:checkbox
                                wire:model="updateApiTokenForm.permissions"
                                :value="$permission"
                            />
                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ $permission }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    wire:click="$set('managingApiTokenPermissions', false)"
                    wire:loading.attr="disabled"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="updateApiToken"
                    wire:loading.attr="disabled"
                    variant="primary"
                    size="sm"
                    icon="check"
                >
                    {{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Token Confirmation Modal -->
    <flux:modal
        wire:model.live="confirmingApiTokenDeletion"
        class="md:w-[500px] lg:w-[600px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="border-b border-gray-200 dark:border-gray-700 pb-6 mb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="trash"
                        class="w-8 h-8 text-red-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-900 dark:text-gray-100"
                        >
                            {{ __('Delete API Token') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
                            {{ __('This action cannot be undone') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                <flux:text class="text-gray-700 dark:text-gray-300 text-sm">
                    {{ __('Are you sure you would like to delete this API token?') }}
                </flux:text>
            </div>

            {{-- Footer Actions --}}
            <div class="flex justify-end items-center pt-6 mt-6 border-t border-gray-200 dark:border-gray-700 gap-3">
                <flux:button
                    wire:click="$toggle('confirmingApiTokenDeletion')"
                    wire:loading.attr="disabled"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="deleteApiToken"
                    wire:loading.attr="disabled"
                    variant="primary"
                    size="sm"
                    icon="trash"
                    class="bg-red-600 hover:bg-red-700 text-white"
                >
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
