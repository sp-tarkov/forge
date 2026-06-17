<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
            {{ __('OAuth Client Moderation') }}
        </h2>
    </x-slot>

    <div class="px-6 lg:px-8 py-6 space-y-6">
        {{-- Filters --}}
        <div class="flex flex-wrap gap-3 items-end">
            <flux:field class="flex-1 min-w-[200px]">
                <flux:label for="search">{{ __('Search by name') }}</flux:label>
                <flux:input
                    id="search"
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="App name fragment"
                />
            </flux:field>

            <flux:field>
                <flux:label for="partyFilter">{{ __('Party') }}</flux:label>
                <flux:select
                    id="partyFilter"
                    wire:model.live="partyFilter"
                >
                    <option value="">{{ __('All') }}</option>
                    <option value="first_party">{{ __('First-party') }}</option>
                    <option value="third_party">{{ __('Third-party') }}</option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label for="statusFilter">{{ __('Status') }}</flux:label>
                <flux:select
                    id="statusFilter"
                    wire:model.live="statusFilter"
                >
                    <option value="">{{ __('All') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="revoked">{{ __('Revoked') }}</option>
                </flux:select>
            </flux:field>
        </div>

        {{-- Client table --}}
        @if ($this->clients->isEmpty())
            <flux:callout
                icon="cube-transparent"
                color="zinc"
                variant="subtle"
            >
                <flux:callout.heading>{{ __('No OAuth clients match these filters') }}</flux:callout.heading>
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Owner') }}</flux:table.column>
                    <flux:table.column>{{ __('Tokens') }}</flux:table.column>
                    <flux:table.column>{{ __('Created') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                @foreach ($this->clients as $client)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium">{{ $client->name }}</span>
                                <span class="text-xs font-mono text-gray-500 dark:text-gray-400 break-all">{{ $client->getKey() }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($client->owner instanceof App\Models\User)
                                <a
                                    href="{{ $client->owner->profile_url }}"
                                    class="underline text-indigo-600 dark:text-indigo-400"
                                >{{ $client->owner->name }}</a>
                            @else
                                <flux:badge
                                    color="indigo"
                                    size="sm"
                                >{{ __('First-party') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $client->tokens_count ?? 0 }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $client->created_at?->diffForHumans() ?? '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($client->revoked)
                                <flux:badge
                                    color="red"
                                    size="sm"
                                >{{ __('Revoked') }}</flux:badge>
                            @else
                                <flux:badge
                                    color="emerald"
                                    size="sm"
                                >{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    icon="document-text"
                                    wire:click="showAuditLog('{{ $client->getKey() }}')"
                                >
                                    {{ __('Audit') }}
                                </flux:button>

                                @unless ($client->revoked)
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        icon="no-symbol"
                                        class="text-red-600 dark:text-red-400"
                                        wire:click="confirmRevocation('{{ $client->getKey() }}')"
                                    >
                                        {{ __('Revoke') }}
                                    </flux:button>
                                @endunless
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table>

            <div>
                {{ $this->clients->links() }}
            </div>
        @endif
    </div>

    {{-- Audit log modal --}}
    <flux:modal
        name="oauth-client-audit"
        class="md:w-[720px]"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Audit log') }}</flux:heading>

            @if ($this->auditTrail->isEmpty())
                <flux:text>{{ __('No recorded events for this client.') }}</flux:text>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($this->auditTrail as $event)
                        <li class="py-3 text-sm space-y-1">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <flux:badge
                                    color="zinc"
                                    size="sm"
                                >{{ $event->event->value }}</flux:badge>
                                <flux:text class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $event->created_at?->diffForHumans() ?? '-' }}
                                </flux:text>
                            </div>
                            <flux:text class="text-xs">
                                @if ($event->actor instanceof App\Models\User)
                                    {{ __('Actor: :name', ['name' => $event->actor->name]) }}
                                @else
                                    {{ __('Actor: (system)') }}
                                @endif
                                @if ($event->ip)
                                    &middot; {{ $event->ip }}
                                @endif
                            </flux:text>
                            @if (is_array($event->metadata) && $event->metadata !== [])
                                <pre class="bg-gray-100 dark:bg-gray-800 rounded p-2 text-xs overflow-x-auto">{{ json_encode($event->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="closeAuditLog"
                    >
                        {{ __('Close') }}
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    {{-- Revoke confirmation modal --}}
    <flux:modal
        name="oauth-client-revoke"
        class="md:w-[480px]"
    >
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Revoke this OAuth client?') }}</flux:heading>

            <flux:text>
                {{ __('Every access and refresh token issued by this client will be revoked immediately. The client row stays in the database with revoked=true for forensic purposes.') }}
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
                    {{ __('Revoke client') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    @script
        <script>
            $wire.on('open-oauth-client-audit-modal', () => $flux.modal('oauth-client-audit').show());
            $wire.on('close-oauth-client-audit-modal', () => $flux.modal('oauth-client-audit').close());
            $wire.on('open-oauth-client-revoke-modal', () => $flux.modal('oauth-client-revoke').show());
            $wire.on('close-oauth-client-revoke-modal', () => $flux.modal('oauth-client-revoke').close());
        </script>
    @endscript
</div>
