<x-layouts::base variant="simple">
    <x-slot:title>
        {{ __('Authorize :name', ['name' => $client->name]) }}
    </x-slot>

    <x-slot:description>
        {{ __(':name is requesting access to your Forge account.', ['name' => $client->name]) }}
    </x-slot>

    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="space-y-6">
            <div class="text-center space-y-3">
                <flux:heading size="lg">
                    {{ __('Authorize :name', ['name' => $client->name]) }}
                </flux:heading>

                @if ($client->firstParty())
                    <flux:badge color="indigo" icon="check-badge">
                        {{ __('Official Forge application') }}
                    </flux:badge>
                @else
                    <flux:badge color="zinc" icon="user-circle">
                        {{ __('Third-party application') }}
                    </flux:badge>
                @endif

                <flux:text class="text-gray-600 dark:text-gray-400">
                    {{ __('Signed in as') }}
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</span>
                </flux:text>
            </div>

            <flux:separator />

            <div>
                <flux:heading size="sm">
                    {{ __('This application will be able to:') }}
                </flux:heading>

                <ul class="mt-3 space-y-2">
                    @foreach ($scopes as $scope)
                        <li class="flex items-start gap-2">
                            <flux:icon
                                name="check"
                                variant="mini"
                                class="mt-0.5 text-emerald-500 shrink-0"
                            />
                            <flux:text class="text-gray-700 dark:text-gray-300">
                                {{ $scope->description }}
                            </flux:text>
                        </li>
                    @endforeach
                </ul>
            </div>

            @unless ($client->firstParty())
                <flux:callout
                    icon="exclamation-triangle"
                    color="amber"
                    variant="subtle"
                >
                    <flux:callout.text>
                        {{ __('You are granting access to an application created by another Forge user. Only approve if you trust this application.') }}
                    </flux:callout.text>
                </flux:callout>
            @endunless

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <form
                    method="POST"
                    action="{{ url('/oauth/authorize') }}"
                    class="flex-1"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="state"
                        value="{{ $request->state }}"
                    />

                    <input
                        type="hidden"
                        name="client_id"
                        value="{{ $client->id }}"
                    />

                    <input
                        type="hidden"
                        name="auth_token"
                        value="{{ $authToken }}"
                    />

                    <flux:button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        icon="check"
                    >
                        {{ __('Authorize') }}
                    </flux:button>
                </form>

                <form
                    method="POST"
                    action="{{ url('/oauth/authorize') }}"
                    class="flex-1"
                >
                    @method('DELETE')
                    @csrf

                    <input
                        type="hidden"
                        name="state"
                        value="{{ $request->state }}"
                    />

                    <input
                        type="hidden"
                        name="client_id"
                        value="{{ $client->id }}"
                    />

                    <input
                        type="hidden"
                        name="auth_token"
                        value="{{ $authToken }}"
                    />

                    <flux:button
                        type="submit"
                        variant="ghost"
                        class="w-full"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                </form>
            </div>
        </div>
    </x-authentication-card>
</x-layouts::base>
