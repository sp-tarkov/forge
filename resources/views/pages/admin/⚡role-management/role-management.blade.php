<div>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    {{ __('Role Management') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        <div class="space-y-6">
            {{-- User Search Section --}}
            <div
                id="search-container"
                class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm"
            >
                <div class="mb-4 flex items-center gap-3">
                    <flux:icon.user-plus class="h-6 w-6 text-gray-400" />
                    <h3 class="text-lg font-semibold text-gray-100">{{ __('Assign Role to User') }}
                    </h3>
                </div>
                <p class="mb-4 text-sm text-gray-400">
                    {{ __('Search for a user by name or email to assign them a role.') }}
                </p>

                <div class="relative max-w-md">
                    <flux:input
                        type="text"
                        wire:model.live.debounce.300ms="userSearch"
                        placeholder="{{ __('Search users by name or email...') }}"
                        icon="magnifying-glass"
                    />

                    {{-- Search Results Dropdown --}}
                    @if ($showUserDropdown && $this->searchResults->isNotEmpty())
                        <div
                            class="absolute z-50 mt-1 max-h-80 w-full overflow-y-auto rounded-lg border border-gray-700 bg-gray-800 shadow-lg">
                            @foreach ($this->searchResults as $user)
                                <button
                                    type="button"
                                    wire:key="search-user-{{ $user->id }}"
                                    wire:click="showAssignRoleModal({{ $user->id }})"
                                    class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-700"
                                >
                                    <flux:avatar
                                        circle
                                        src="{{ $user->profile_photo_url }}"
                                        color="auto"
                                        color:seed="{{ $user->id }}"
                                        size="sm"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate text-sm font-medium text-gray-100">
                                                {{ $user->name }}
                                            </span>
                                            @if ($user->role)
                                                <flux:badge
                                                    color="{{ $user->role->color_class }}"
                                                    size="sm"
                                                >{{ $user->role->short_name }}</flux:badge>
                                            @endif
                                        </div>
                                        <p class="truncate text-xs text-gray-400">
                                            {{ $user->email }}</p>
                                    </div>
                                    <flux:icon.chevron-right class="h-4 w-4 flex-shrink-0 text-gray-400" />
                                </button>
                            @endforeach
                        </div>
                    @elseif ($showUserDropdown && $this->searchResults->isEmpty())
                        <div
                            class="absolute z-50 mt-1 w-full rounded-lg border border-gray-700 bg-gray-800 p-4 text-center shadow-lg">
                            <p class="text-sm text-gray-400">
                                {{ __('No users found matching your search.') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Users with Roles Table --}}
            <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900 shadow-sm">
                <div class="border-b border-gray-700 p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-lg font-semibold text-gray-100">
                            {{ __('Users with Roles') }} ({{ number_format($this->usersWithRoles->total()) }})
                        </h3>

                        {{-- Role Filter --}}
                        <div class="w-full sm:w-48">
                            <flux:select
                                wire:model.live="roleFilter"
                                size="sm"
                                variant="listbox"
                            >
                                <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
                                @foreach ($this->roles as $role)
                                    <flux:select.option value="{{ $role->id }}">{{ $role->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </div>

                {{-- Top Pagination --}}
                @if ($this->usersWithRoles->hasPages())
                    <div class="border-b border-gray-700 bg-gray-800 px-6 py-4">
                        {{ $this->usersWithRoles->links(data: ['scrollTo' => '#search-container']) }}
                    </div>
                @endif

                <div class="w-full overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    {{ __('User') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-400">
                                    {{ __('Role') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium tracking-wider text-gray-400">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-800">
                            @forelse ($this->usersWithRoles as $user)
                                <tr
                                    wire:key="role-user-{{ $user->id }}"
                                    class="hover:bg-gray-700"
                                >
                                    {{-- User Column --}}
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar
                                                circle
                                                src="{{ $user->profile_photo_url }}"
                                                color="auto"
                                                color:seed="{{ $user->id }}"
                                                size="sm"
                                            />
                                            <div class="min-w-0">
                                                <a
                                                    href="{{ $user->profile_url }}"
                                                    class="block max-w-48 truncate text-sm font-medium text-gray-100 underline hover:text-gray-300"
                                                    wire:navigate
                                                >
                                                    {{ $user->name }}
                                                </a>
                                                <p class="truncate text-xs text-gray-400">
                                                    {{ $user->email }}</p>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Role Column --}}
                                    <td class="whitespace-nowrap px-4 py-4">
                                        @if ($user->role)
                                            <flux:badge
                                                color="{{ $user->role->color_class }}"
                                                size="sm"
                                            >
                                                {{ $user->role->name }}
                                            </flux:badge>
                                        @endif
                                    </td>

                                    {{-- Actions Column --}}
                                    <td class="whitespace-nowrap px-4 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button
                                                wire:click="showAssignRoleModal({{ $user->id }})"
                                                variant="outline"
                                                size="xs"
                                                icon="pencil"
                                            >
                                                {{ __('Change') }}
                                            </flux:button>
                                            <flux:button
                                                wire:click="showRemoveRoleModal({{ $user->id }})"
                                                variant="danger"
                                                size="xs"
                                                icon="trash"
                                            >
                                                {{ __('Remove') }}
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="3"
                                        class="px-6 py-12 text-center text-gray-400"
                                    >
                                        <flux:icon.user-group class="mx-auto mb-4 h-12 w-12 text-gray-600" />
                                        <p class="text-gray-400">
                                            {{ __('No users with roles found.') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Bottom Pagination --}}
                @if ($this->usersWithRoles->hasPages())
                    <div class="border-t border-gray-700 px-6 py-4">
                        {{ $this->usersWithRoles->links(data: ['scrollTo' => '#search-container']) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Assign Role Modal --}}
    <flux:modal
        wire:model.self="showAssignModal"
        class="md:w-[450px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon.user-plus class="h-8 w-8 text-blue-600" />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Assign Role') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Select a role to assign to this user') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-6">
                {{-- Selected User Info --}}
                @if ($this->selectedUser)
                    <div class="flex items-center gap-3 rounded-lg bg-gray-800 p-4">
                        <flux:avatar
                            circle
                            src="{{ $this->selectedUser->profile_photo_url }}"
                            color="auto"
                            color:seed="{{ $this->selectedUser->id }}"
                            size="md"
                        />
                        <div>
                            <p class="font-medium text-gray-100">{{ $this->selectedUser->name }}</p>
                            <p class="text-sm text-gray-400">{{ $this->selectedUser->email }}</p>
                            @if ($this->selectedUser->role)
                                <div class="mt-1">
                                    <span class="text-xs text-gray-400">{{ __('Current role:') }}</span>
                                    <flux:badge
                                        color="{{ $this->selectedUser->role->color_class }}"
                                        size="sm"
                                    >{{ $this->selectedUser->role->name }}</flux:badge>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Role Selection --}}
                <div>
                    <flux:select
                        variant="listbox"
                        wire:model="selectedRoleId"
                        label="{{ __('Select Role') }}"
                    >
                        <flux:select.option value="">{{ __('Choose a role...') }}</flux:select.option>
                        @foreach ($this->roles as $role)
                            <flux:select.option value="{{ $role->id }}">{{ $role->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('selectedRoleId')
                        <flux:text
                            size="xs"
                            variant="danger"
                            class="mt-1"
                        >{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    wire:click="closeAssignModal"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="assignRole"
                    variant="primary"
                    size="sm"
                    icon="check"
                >
                    {{ __('Assign Role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Remove Role Modal --}}
    <flux:modal
        wire:model.self="showRemoveModal"
        class="md:w-[400px]"
    >
        <div class="space-y-0">
            {{-- Header Section --}}
            <div class="mb-6 border-b border-gray-700 pb-6">
                <div class="flex items-center gap-3">
                    <flux:icon.exclamation-triangle class="h-8 w-8 text-red-600" />
                    <div>
                        <flux:heading
                            size="xl"
                            class="text-gray-100"
                        >
                            {{ __('Remove Role') }}
                        </flux:heading>
                        <flux:text class="mt-1 text-sm text-gray-400">
                            {{ __('Confirm role removal') }}
                        </flux:text>
                    </div>
                </div>
            </div>

            {{-- Content Section --}}
            <div class="space-y-4">
                @if ($this->userToRemoveRole)
                    <flux:text class="text-gray-300">
                        {{ __('Are you sure you want to remove the') }}
                        <flux:badge
                            color="{{ $this->userToRemoveRole->role?->color_class }}"
                            size="sm"
                        >{{ $this->userToRemoveRole->role?->name }}</flux:badge>
                        {{ __('role from') }}
                        <span class="font-medium">{{ $this->userToRemoveRole->name }}</span>?
                    </flux:text>
                    <flux:text class="text-sm text-gray-400">
                        {{ __('This user will lose all privileges associated with this role.') }}
                    </flux:text>
                @endif
            </div>

            {{-- Footer Actions --}}
            <div class="mt-6 flex justify-end gap-3 border-t border-gray-700 pt-6">
                <flux:button
                    wire:click="closeRemoveModal"
                    variant="outline"
                    size="sm"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    wire:click="removeRole"
                    variant="danger"
                    size="sm"
                    icon="trash"
                >
                    {{ __('Remove Role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
