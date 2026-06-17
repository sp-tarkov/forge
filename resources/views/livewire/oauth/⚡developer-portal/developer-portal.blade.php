<x-slot:title>
    {{ __('OAuth Apps - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Register and manage OAuth applications that authenticate against The Forge.') }}
</x-slot>

<div>
    <x-action-section>
        <x-slot name="title">
            {{ __('Your OAuth Apps') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Apps you register here let other users authorize access to their Forge account via the standard OAuth 2.1 Authorization Code with PKCE flow. You may register up to :count apps.', ['count' => $this->clientLimit]) }}
        </x-slot>

        <x-slot name="content">
            @unless ($this->canCreate)
                <flux:callout
                    icon="exclamation-triangle"
                    color="amber"
                    variant="subtle"
                    class="mb-6"
                >
                    <flux:callout.text>
                        {{ __('You have reached the maximum of :count apps. Delete one to register another.', ['count' => $this->clientLimit]) }}
                    </flux:callout.text>
                </flux:callout>
            @endunless

            @if ($this->clients->isEmpty())
                <flux:callout
                    icon="cube-transparent"
                    color="zinc"
                    variant="subtle"
                >
                    <flux:callout.heading>{{ __('No apps yet') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('When you register an app you will receive a client ID (and, for confidential clients, a one-time secret) you can use to drive the OAuth flow.') }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <div class="space-y-4">
                    @foreach ($this->clients as $client)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <flux:heading size="md">{{ $client->name }}</flux:heading>

                                        @if ($client->secret !== null)
                                            <flux:badge
                                                color="indigo"
                                                size="sm"
                                            >
                                                {{ __('Confidential') }}
                                            </flux:badge>
                                        @else
                                            <flux:badge
                                                color="zinc"
                                                size="sm"
                                            >
                                                {{ __('Public (PKCE)') }}
                                            </flux:badge>
                                        @endif
                                    </div>

                                    @if (! empty($client->description))
                                        <flux:text class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $client->description }}
                                        </flux:text>
                                    @endif

                                    <dl class="mt-3 grid grid-cols-1 gap-2 text-sm">
                                        <div class="flex gap-2 items-center">
                                            <dt class="text-gray-500 dark:text-gray-400 w-28 shrink-0">{{ __('Client ID') }}</dt>
                                            <dd class="min-w-0 flex-1">
                                                <flux:input
                                                    value="{{ $client->getKey() }}"
                                                    readonly
                                                    copyable
                                                    size="sm"
                                                    class="font-mono"
                                                />
                                            </dd>
                                        </div>

                                        @if (! empty($client->homepage_url))
                                            <div class="flex gap-2 items-baseline">
                                                <dt class="text-gray-500 dark:text-gray-400 w-28 shrink-0">{{ __('Homepage') }}</dt>
                                                <dd class="min-w-0 flex-1 break-all">
                                                    <a
                                                        href="{{ $client->homepage_url }}"
                                                        rel="noopener nofollow"
                                                        target="_blank"
                                                        class="text-indigo-600 dark:text-indigo-400 hover:underline"
                                                    >{{ $client->homepage_url }}</a>
                                                </dd>
                                            </div>
                                        @endif

                                        <div class="flex gap-2">
                                            <dt class="text-gray-500 dark:text-gray-400 w-28 shrink-0">{{ __('Redirect URIs') }}</dt>
                                            <dd class="space-y-1 text-xs break-all text-gray-700 dark:text-gray-300">
                                                @foreach (is_array($client->redirect_uris) ? $client->redirect_uris : [] as $uri)
                                                    <div class="font-mono">{{ $uri }}</div>
                                                @endforeach
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        inset="top right"
                                        icon="pencil"
                                        wire:click="editClient('{{ $client->getKey() }}')"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>

                                    @if ($client->secret !== null)
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            inset="right"
                                            icon="arrow-path"
                                            wire:click="confirmRegenerate('{{ $client->getKey() }}')"
                                        >
                                            {{ __('New secret') }}
                                        </flux:button>
                                    @endif

                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        inset="right"
                                        icon="trash"
                                        class="text-red-600 dark:text-red-400"
                                        wire:click="confirmDeletion('{{ $client->getKey() }}')"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-slot>
    </x-action-section>

    {{-- Create / edit modal --}}
    <flux:modal
        name="developer-portal"
        class="md:w-[640px]"
    >
        <form
            wire:submit="save"
            class="space-y-6"
        >
            <div>
                <flux:heading size="lg">
                    {{ $editingClientId === null ? __('Register OAuth app') : __('Edit OAuth app') }}
                </flux:heading>

                <flux:text class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ __('Users will see your app name and description on the authorization screen when they grant access.') }}
                </flux:text>
            </div>

            <flux:field>
                <flux:label for="form.name">{{ __('App name') }}</flux:label>
                <flux:input
                    id="form.name"
                    type="text"
                    wire:model="form.name"
                    placeholder="My Cool Launcher"
                />
                <flux:error name="form.name" />
            </flux:field>

            <flux:field>
                <flux:label for="form.description">{{ __('Description') }}</flux:label>
                <flux:textarea
                    id="form.description"
                    wire:model="form.description"
                    rows="3"
                    placeholder="What does this app do?"
                />
                <flux:error name="form.description" />
            </flux:field>

            <flux:field>
                <flux:label for="form.homepage_url">{{ __('Homepage URL (optional)') }}</flux:label>
                <flux:input
                    id="form.homepage_url"
                    type="url"
                    wire:model="form.homepage_url"
                    placeholder="https://example.com"
                />
                <flux:error name="form.homepage_url" />
            </flux:field>

            <flux:field>
                <flux:label for="form.redirect_uris_raw">{{ __('Redirect URIs (one per line)') }}</flux:label>
                <flux:textarea
                    id="form.redirect_uris_raw"
                    wire:model="form.redirect_uris_raw"
                    rows="3"
                    placeholder="http://127.0.0.1/callback&#10;https://example.com/oauth/callback"
                />
                <flux:description>
                    {{ __('Loopback hosts (127.0.0.1, [::1]) match any port at runtime per RFC 8252.') }}
                </flux:description>
                <flux:error name="form.redirect_uris_raw" />
                <flux:error name="form.redirect_uris.*" />
            </flux:field>

            <flux:field variant="inline">
                <flux:switch
                    id="form.confidential"
                    wire:model="form.confidential"
                    :disabled="$editingClientId !== null"
                />
                <flux:label for="form.confidential">{{ __('Confidential client') }}</flux:label>
                <flux:description>
                    {{ __('Turn this on only if your app runs on a server you control, where a secret can be stored safely (for example, a website backend). Turn it off for apps that run on the user device, such as desktop launchers, mobile apps, or single-page web apps: they cannot hide a secret, so they sign in with PKCE instead (a public client). This cannot be changed after the app is created.') }}
                </flux:description>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button
                        type="button"
                        variant="ghost"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="submit"
                    variant="primary"
                >
                    {{ $editingClientId === null ? __('Create') : __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- One-time secret reveal modal --}}
    <flux:modal
        name="developer-portal-secret"
        class="md:w-[640px]"
        :dismissible="false"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Save this secret now') }}</flux:heading>

            <flux:callout
                icon="exclamation-triangle"
                color="amber"
                variant="subtle"
            >
                <flux:callout.text>
                    {{ __('This is the only time we will show you this secret. Copy it into your application now. You will not be able to retrieve it again.') }}
                </flux:callout.text>
            </flux:callout>

            <flux:input
                value="{{ $plainSecret }}"
                readonly
                copyable
                class="font-mono"
            />

            <div class="flex justify-end">
                <flux:button
                    type="button"
                    variant="primary"
                    wire:click="dismissSecret"
                >
                    {{ __('I have copied my secret') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Regenerate secret confirmation modal --}}
    <flux:modal
        name="developer-portal-regenerate"
        class="md:w-[480px]"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Regenerate client secret?') }}</flux:heading>

            <flux:text>
                {{ __('The current secret stops working immediately. Any app using it must be updated with the new secret before it can authenticate again.') }}
            </flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button
                        type="button"
                        variant="ghost"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="regenerateSecret"
                >
                    {{ __('Regenerate secret') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delete confirmation modal --}}
    <flux:modal
        name="developer-portal-delete"
        class="md:w-[480px]"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete OAuth app?') }}</flux:heading>

            <flux:text>
                {{ __('All access and refresh tokens issued to this client will be revoked immediately. Users who have authorized the app will need to authorize it again if you re-register it.') }}
            </flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button
                        type="button"
                        variant="ghost"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:button
                    type="button"
                    variant="danger"
                    wire:click="deleteClient"
                >
                    {{ __('Delete app') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    @script
        <script>
            $wire.on('open-developer-portal-modal', () => $flux.modal('developer-portal').show());
            $wire.on('close-developer-portal-modal', () => $flux.modal('developer-portal').close());
            $wire.on('show-developer-portal-secret', () => $flux.modal('developer-portal-secret').show());
            $wire.on('close-developer-portal-secret', () => $flux.modal('developer-portal-secret').close());
            $wire.on('open-developer-portal-delete-modal', () => $flux.modal('developer-portal-delete').show());
            $wire.on('close-developer-portal-delete-modal', () => $flux.modal('developer-portal-delete').close());
            $wire.on('open-developer-portal-regenerate-modal', () => $flux.modal('developer-portal-regenerate').show());
            $wire.on('close-developer-portal-regenerate-modal', () => $flux.modal('developer-portal-regenerate').close());
        </script>
    @endscript
</div>
