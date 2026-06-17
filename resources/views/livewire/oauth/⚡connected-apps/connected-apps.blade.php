<x-slot:title>
    {{ __('Connected Apps - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('Review and revoke applications you have authorized to access your Forge account.') }}
</x-slot>

<div>
    <x-action-section>
        <x-slot name="title">
            {{ __('Authorized applications') }}
        </x-slot>

        <x-slot name="description">
            {{ __('These applications have been granted access to your Forge account via OAuth. Revoke a single device when you stop using it on that machine, or revoke the entire app to invalidate every device at once.') }}
        </x-slot>

        <x-slot name="content">
            @if ($this->connections->isEmpty())
                <flux:callout
                    icon="cube-transparent"
                    color="zinc"
                    variant="subtle"
                >
                    <flux:callout.heading>{{ __('No authorized apps') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Once you authorize an app like The Forge Launcher it will show up here.') }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <div class="space-y-6">
                    @foreach ($this->connections as $entry)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <flux:heading size="md">{{ $entry['client']->name }}</flux:heading>

                                        @if ($entry['isFirstParty'])
                                            <flux:badge
                                                color="indigo"
                                                icon="check-badge"
                                                size="sm"
                                            >
                                                {{ __('First-party') }}
                                            </flux:badge>
                                        @else
                                            <flux:badge
                                                color="zinc"
                                                icon="user-circle"
                                                size="sm"
                                            >
                                                {{ __('Third-party') }}
                                            </flux:badge>
                                        @endif
                                    </div>

                                    @if (! empty($entry['client']->description))
                                        <flux:text class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $entry['client']->description }}
                                        </flux:text>
                                    @endif

                                    @if (! empty($entry['client']->homepage_url))
                                        <flux:text class="mt-1 text-sm">
                                            <a
                                                href="{{ $entry['client']->homepage_url }}"
                                                target="_blank"
                                                rel="noopener nofollow"
                                                class="underline text-indigo-600 dark:text-indigo-400 break-all"
                                            >{{ $entry['client']->homepage_url }}</a>
                                        </flux:text>
                                    @endif
                                </div>

                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-600 dark:text-red-400 shrink-0"
                                    wire:click="confirmRevokeClient('{{ $entry['client']->getKey() }}')"
                                >
                                    {{ __('Revoke app') }}
                                </flux:button>
                            </div>

                            <div class="mt-4">
                                <flux:heading
                                    size="sm"
                                    class="text-gray-700 dark:text-gray-300"
                                >
                                    {{ __('Active devices (:count)', ['count' => $entry['totalIssued']]) }}
                                </flux:heading>

                                <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($entry['tokens'] as $token)
                                        <li class="flex items-center justify-between py-2 gap-4">
                                            <div class="min-w-0 flex-1 space-y-1">
                                                <flux:text class="font-medium">
                                                    {{ $token->getAttribute('device_name') ?: __('Unnamed device') }}
                                                </flux:text>

                                                <flux:text class="text-xs text-gray-500 dark:text-gray-400">
                                                    @if (! empty($token->lastUsedHuman))
                                                        {{ __('Last used :diff', ['diff' => $token->lastUsedHuman]) }}
                                                    @else
                                                        {{ __('Never used') }}
                                                    @endif

                                                    @if ($token->getAttribute('last_ip') !== null)
                                                        &middot; {{ $token->getAttribute('last_ip') }}
                                                    @endif

                                                    &middot; {{ __('Issued :diff', ['diff' => $token->createdHuman]) }}
                                                </flux:text>

                                                @if (! empty($token->getAttribute('scopes')))
                                                    <ul class="mt-1 space-y-0.5">
                                                        @foreach ($token->getAttribute('scopes') as $scope)
                                                            <li class="flex items-start gap-1.5">
                                                                <flux:icon
                                                                    name="check"
                                                                    variant="mini"
                                                                    class="mt-0.5 text-emerald-500 shrink-0"
                                                                />
                                                                <flux:text class="text-xs text-gray-500 dark:text-gray-400">
                                                                    {{ $this->scopeDescriptions[$scope] ?? $scope }}
                                                                </flux:text>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </div>

                                            <flux:button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                icon="x-mark"
                                                wire:click="confirmRevokeDevice('{{ $token->getKey() }}')"
                                            >
                                                {{ __('Revoke') }}
                                            </flux:button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-slot>
    </x-action-section>

    {{-- Revoke single device --}}
    <flux:modal
        name="connected-apps-revoke-device"
        class="md:w-[480px]"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Revoke this device?') }}</flux:heading>

            <flux:text>
                {{ __('The app will be signed out on this device immediately. Your other devices keep their access.') }}
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
                    wire:click="revokeDevice"
                >
                    {{ __('Revoke device') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Revoke entire app --}}
    <flux:modal
        name="connected-apps-revoke-client"
        class="md:w-[480px]"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Revoke this application?') }}</flux:heading>

            <flux:text>
                {{ __('Every device using this app will be signed out immediately. You will need to authorize the app again on each device the next time you use it.') }}
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
                    wire:click="revokeClient"
                >
                    {{ __('Revoke app') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    @script
        <script>
            $wire.on('open-connected-apps-revoke-device-modal', () => $flux.modal('connected-apps-revoke-device').show());
            $wire.on('close-connected-apps-revoke-device-modal', () => $flux.modal('connected-apps-revoke-device').close());
            $wire.on('open-connected-apps-revoke-client-modal', () => $flux.modal('connected-apps-revoke-client').show());
            $wire.on('close-connected-apps-revoke-client-modal', () => $flux.modal('connected-apps-revoke-client').close());
        </script>
    @endscript
</div>
