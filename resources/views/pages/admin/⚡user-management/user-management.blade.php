<div>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    {{ __('User Management') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        @if ($this->getActiveFilters())
            <div class="my-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                    <span class="flex-shrink-0 text-sm font-medium text-gray-300">Filtering:</span>
                    <div class="min-w-0">
                        <flux:breadcrumbs class="inline-flex flex-wrap">
                            @foreach ($this->getActiveFilters() as $index => $filter)
                                <flux:breadcrumbs.item separator="slash">{{ $filter }}</flux:breadcrumbs.item>
                            @endforeach
                        </flux:breadcrumbs>
                    </div>
                </div>
            </div>
        @endif

        {{-- Flash Messages --}}
        @if (session()->has('success'))
            <div class="mb-6 rounded-lg border border-green-800 bg-green-900/20 p-4">
                <div class="flex items-center">
                    <flux:icon.check-circle class="mr-2 h-5 w-5 text-green-400" />
                    <p class="text-sm text-green-200">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="mb-6 rounded-lg border border-red-800 bg-red-900/20 p-4">
                <div class="flex items-center">
                    <flux:icon.x-circle class="mr-2 h-5 w-5 text-red-400" />
                    <p class="text-sm text-red-200">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <div class="space-y-6">
            {{-- Filters Section --}}
            <div
                id="filters-container"
                class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm"
                x-data="{
                    search: $wire.entangle('search').live,
                    clearSearch() {
                        this.search = '';
                    }
                }"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="resetFilters"
                        x-on:click="clearSearch()"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear All
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {{-- Search Filter --}}
                    <div wire:ignore.self>
                        <flux:label
                            for="search"
                            class="text-xs"
                        >Search</flux:label>
                        <flux:input
                            type="text"
                            x-model.debounce.300ms="search"
                            id="search"
                            placeholder="Name, email, or ID..."
                            size="sm"
                        />
                    </div>

                    {{-- Role Filter --}}
                    <div>
                        <flux:label
                            for="roleFilter"
                            class="text-xs"
                        >Role</flux:label>
                        <flux:select
                            wire:model.live="roleFilter"
                            id="roleFilter"
                            size="sm"
                            variant="listbox"
                        >
                            <flux:select.option value="">All Roles</flux:select.option>
                            @foreach ($this->roles as $role)
                                <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    {{-- Status Filter --}}
                    <div>
                        <flux:label
                            for="statusFilter"
                            class="text-xs"
                        >Status</flux:label>
                        <flux:select
                            variant="listbox"
                            wire:model.live="statusFilter"
                            id="statusFilter"
                            size="sm"
                        >
                            <flux:select.option value="">All Users</flux:select.option>
                            <flux:select.option value="active">Active Only</flux:select.option>
                            <flux:select.option value="banned">Banned Only</flux:select.option>
                        </flux:select>
                    </div>

                    {{-- Date Range Filters --}}
                    <div>
                        <flux:date-picker
                            wire:model.live="joinedFrom"
                            label="Joined From"
                            size="sm"
                            clearable
                        />
                    </div>

                    <div>
                        <flux:date-picker
                            wire:model.live="joinedTo"
                            label="Joined To"
                            size="sm"
                            clearable
                        />
                    </div>
                </div>
            </div>

            {{-- Users Table --}}
            <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900 shadow-sm">
                <div class="border-b border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-100">Users
                        ({{ number_format($this->users->total()) }})</h3>
                </div>

                {{-- Top Pagination --}}
                @if ($this->users->hasPages())
                    <div class="border-b border-gray-700 bg-gray-800 px-6 py-4">
                        {{ $this->users->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table
                        class="w-full table-auto"
                        style="min-width: 1000px;"
                    >
                        <thead class="bg-gray-900">
                            <tr>
                                <th
                                    class="px-2 py-2 text-left text-xs font-medium tracking-wider text-gray-400 sm:px-3">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('name')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>User</span>
                                        @if ($sortBy === 'name')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 py-2 text-left text-xs font-medium tracking-wider text-gray-400 sm:px-3">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('user_role_id')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Role</span>
                                        @if ($sortBy === 'user_role_id')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 py-2 text-left text-xs font-medium tracking-wider text-gray-400 sm:px-3">
                                    Ban Status</th>
                                <th
                                    class="px-2 py-2 text-left text-xs font-medium tracking-wider text-gray-400 sm:px-3">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('email_verified_at')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Email</span>
                                        @if ($sortBy === 'email_verified_at')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 py-2 text-center text-xs font-medium tracking-wider text-gray-400 sm:px-3">
                                    MFA</th>
                                <th
                                    class="px-2 py-2 text-left text-xs font-medium tracking-wider text-gray-400 sm:px-3">
                                    Content</th>
                                <th
                                    class="px-2 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-400 sm:px-3">
                                    <button
                                        type="button"
                                        wire:click="sortByColumn('created_at')"
                                        class="flex w-full cursor-pointer select-none items-center space-x-1 text-left hover:text-gray-300"
                                    >
                                        <span>Joined</span>
                                        @if ($sortBy === 'created_at')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </button>
                                </th>
                                <th
                                    class="px-2 py-2 text-left text-xs font-medium tracking-wider text-gray-400 sm:px-3">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-800">
                            @forelse($this->users as $user)
                                <tr class="hover:bg-gray-700">
                                    {{-- User Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 sm:px-3">
                                        <div class="flex items-center space-x-2">
                                            <flux:avatar
                                                circle="circle"
                                                src="{{ $user->profile_photo_url }}"
                                                color="auto"
                                                color:seed="{{ $user->id }}"
                                                size="sm"
                                            />
                                            <div class="min-w-0">
                                                <a
                                                    href="{{ $user->profile_url }}"
                                                    class="block max-w-32 truncate text-sm font-medium underline hover:text-gray-300 lg:max-w-48"
                                                >
                                                    <x-user-name :user="$user" />
                                                </a>
                                                <p class="text-xs text-gray-400">
                                                    {{ $user->email }}</p>
                                                <p class="text-xs text-gray-500">ID:
                                                    {{ $user->id }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Role Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 sm:px-3">
                                        @if ($user->role)
                                            <flux:badge
                                                color="{{ $user->role->color_class }}"
                                                size="sm"
                                            >
                                                {{ $user->role->short_name }}
                                            </flux:badge>
                                        @else
                                            <flux:badge
                                                color="gray"
                                                size="sm"
                                            >User</flux:badge>
                                        @endif
                                    </td>

                                    {{-- Ban Status Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 sm:px-3">
                                        @if ($activeBan = $this->getActiveBan($user))
                                            <div class="flex items-center gap-2">
                                                <flux:badge
                                                    color="red"
                                                    size="sm"
                                                >Banned</flux:badge>
                                                <flux:tooltip>
                                                    <flux:icon.information-circle
                                                        class="h-4 w-4 cursor-help text-gray-500 hover:text-gray-300"
                                                    />
                                                    <flux:tooltip.content>
                                                        <div class="text-sm">
                                                            <div class="font-medium">Ban Details</div>
                                                            <div class="mt-1">Reason:
                                                                {{ $activeBan->comment ?: 'No reason provided' }}</div>
                                                            <div>Date:
                                                                {{ $activeBan->created_at->format('M j, Y g:i A') }}
                                                            </div>
                                                            <div>Expires:
                                                                {{ $activeBan->expired_at ? $activeBan->expired_at->format('M j, Y g:i A') : 'Permanent' }}
                                                            </div>
                                                        </div>
                                                    </flux:tooltip.content>
                                                </flux:tooltip>
                                            </div>
                                        @else
                                            <flux:badge
                                                color="green"
                                                size="sm"
                                            >Active</flux:badge>
                                        @endif
                                    </td>

                                    {{-- Email Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 sm:px-3">
                                        <div class="flex items-center gap-2">
                                            @if ($user->email_verified_at)
                                                <flux:badge
                                                    color="green"
                                                    size="sm"
                                                >Verified</flux:badge>
                                            @else
                                                <flux:badge
                                                    color="red"
                                                    size="sm"
                                                >Unverified</flux:badge>
                                            @endif
                                            @if ($user->hasDisposableEmail())
                                                <flux:tooltip>
                                                    <flux:icon.exclamation-triangle class="h-4 w-4 text-amber-400" />
                                                    <flux:tooltip.content>
                                                        <div class="text-sm">
                                                            This email address is flagged as being disposable
                                                        </div>
                                                    </flux:tooltip.content>
                                                </flux:tooltip>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- MFA Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 text-center sm:px-3">
                                        @if ($user->hasMfaEnabled())
                                            <flux:icon.shield-check class="mx-auto h-4 w-4 text-green-400" />
                                        @else
                                            <flux:icon.shield-exclamation class="mx-auto h-4 w-4 text-red-400" />
                                        @endif
                                    </td>

                                    {{-- Content Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 sm:px-3">
                                        <div class="text-xs text-gray-100">
                                            <div>Mods: {{ number_format($user->mods_count) }}</div>
                                            <div>Comments: {{ number_format($user->comments_count) }}</div>
                                        </div>
                                    </td>

                                    {{-- Joined Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 text-xs text-gray-100 sm:px-3">
                                        <div>{{ $user->created_at->format('M j, Y') }}</div>
                                        <div class="text-gray-400">
                                            {{ $user->created_at->diffForHumans() }}</div>
                                    </td>

                                    {{-- Actions Column --}}
                                    <td class="whitespace-nowrap px-2 py-2 sm:px-3">
                                        <flux:dropdown align="end">
                                            <flux:button
                                                variant="outline"
                                                size="xs"
                                                icon="ellipsis-horizontal"
                                            >
                                                Actions
                                            </flux:button>
                                            <flux:menu>
                                                <flux:menu.item
                                                    icon="eye"
                                                    wire:navigate
                                                    href="{{ $user->profile_url }}"
                                                >
                                                    View Profile
                                                </flux:menu.item>
                                                <flux:menu.item
                                                    icon="computer-desktop"
                                                    wire:click="showUserIpAddresses({{ $user->id }})"
                                                >
                                                    View IP Addresses
                                                </flux:menu.item>
                                                @if (!$user->isAdmin())
                                                    <flux:menu.separator />
                                                    @if ($activeBan)
                                                        <flux:menu.item
                                                            icon="check-circle"
                                                            wire:click="showUnbanUser({{ $user->id }})"
                                                        >
                                                            Unban User
                                                        </flux:menu.item>
                                                    @else
                                                        <flux:menu.item
                                                            icon="no-symbol"
                                                            wire:click="showBanUser({{ $user->id }})"
                                                        >
                                                            Ban User
                                                        </flux:menu.item>
                                                    @endif
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="8"
                                        class="px-6 py-12 text-center text-gray-400"
                                    >
                                        <flux:icon.users class="mx-auto mb-4 h-12 w-12 text-gray-600" />
                                        <p class="text-gray-400">No users found for the selected
                                            filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Pagination --}}
                @if ($this->users->hasPages())
                    <div class="border-t border-gray-700 px-6 py-4">
                        {{ $this->users->links(data: ['scrollTo' => '#filters-container']) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Ban User Modal --}}
    <flux:modal
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
                        wire:model.live="banDuration"
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
                    @error('banDuration')
                        <flux:text
                            size="xs"
                            variant="danger"
                            class="mt-1"
                        >{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:textarea
                        wire:model.live="banReason"
                        label="{{ __('Reason (optional)') }}"
                        placeholder="{{ __('Please provide a reason for this ban...') }}"
                        rows="3"
                    />
                    @error('banReason')
                        <flux:text
                            size="xs"
                            variant="danger"
                            class="mt-1"
                        >{{ $message }}</flux:text>
                    @enderror
                </div>
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
                        wire:click="closeBanModal"
                        variant="outline"
                        size="sm"
                    >
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button
                        wire:click="banUser"
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

    {{-- Unban User Modal --}}
    <flux:modal
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
                    wire:click="closeUnbanModal"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="unbanUser"
                    variant="primary"
                    size="sm"
                    icon="shield-check"
                >
                    {{ __('Unban User') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- IP Addresses Modal --}}
    <flux:modal
        wire:model.self="showIpModal"
        variant="flyout"
    >
        <div class="space-y-6">
            <div class="border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon
                        name="computer-desktop"
                        class="h-8 w-8 text-blue-600"
                    />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            IP Addresses
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            @if ($this->selectedUser)
                                IP addresses used by {{ $this->selectedUser->name }}
                            @endif
                        </flux:text>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                {{-- IP Blocking Warning --}}
                <flux:callout
                    variant="warning"
                    icon="exclamation-triangle"
                >
                    <flux:callout.heading>Important</flux:callout.heading>
                    <flux:callout.text>
                        <p class="mb-2">IP blocking should only be used in extreme cases, such as harassment or abuse
                            situations. IP addresses may be shared among multiple users, masked by VPNs, or changed
                            easily by users.</p>
                        <p><strong>Temporary blocks:</strong> IP bans are automatically set to expire after one month
                            and should not be relied upon as a permanent solution.</p>
                    </flux:callout.text>
                </flux:callout>

                @forelse($userIpAddresses as $ipData)
                    <div class="flex items-center justify-between rounded-lg border border-gray-700 p-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm text-gray-100">{{ $ipData->ip }}</span>
                                @if ($ipData->is_banned)
                                    <flux:badge
                                        color="red"
                                        size="sm"
                                    >Banned</flux:badge>
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-400">
                                <div>First seen:
                                    {{ \Carbon\Carbon::parse($ipData->first_seen)->format('M j, Y g:i A') }}</div>
                                <div>Last seen: {{ \Carbon\Carbon::parse($ipData->last_seen)->format('M j, Y g:i A') }}
                                </div>
                                <div>Usage count: {{ number_format($ipData->usage_count) }}</div>
                            </div>
                        </div>
                        <div>
                            @if ($ipData->ip === request()->ip())
                                <flux:tooltip>
                                    <div class="inline-block">
                                        <flux:button
                                            variant="{{ $ipData->is_banned ? 'primary' : 'danger' }}"
                                            size="xs"
                                            disabled
                                        >
                                            {{ $ipData->is_banned ? 'Unban IP' : 'Ban IP' }}
                                        </flux:button>
                                    </div>
                                    <flux:tooltip.content>
                                        <div class="text-sm">
                                            <div class="font-medium">Cannot ban current IP</div>
                                            <div class="mt-1">This is your current IP address. Banning it would lock
                                                you out of the system.</div>
                                        </div>
                                    </flux:tooltip.content>
                                </flux:tooltip>
                            @else
                                <flux:button
                                    wire:click="toggleIpBan('{{ $ipData->ip }}')"
                                    variant="{{ $ipData->is_banned ? 'primary' : 'danger' }}"
                                    size="xs"
                                >
                                    {{ $ipData->is_banned ? 'Unban IP' : 'Ban IP' }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center">
                        <flux:icon.computer-desktop class="mx-auto mb-4 h-12 w-12 text-gray-600" />
                        <p class="text-gray-400">No IP addresses found for this user.</p>
                    </div>
                @endforelse
            </div>

            <div class="flex items-center justify-end border-t border-gray-700 pt-6">
                <flux:button
                    wire:click="closeIpModal"
                    variant="outline"
                >
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
