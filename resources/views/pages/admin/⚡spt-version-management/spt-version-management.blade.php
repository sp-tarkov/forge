<div>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    {{ __('SPT Version Management') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        <div class="space-y-6">
            {{-- Actions Bar --}}
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-2">
                    <flux:button
                        wire:click="showCreateVersion"
                        variant="primary"
                        icon="plus"
                    >
                        Create Version
                    </flux:button>

                    <flux:button
                        wire:click="syncFromGitHub"
                        variant="outline"
                        icon="arrow-path"
                        wire:loading.attr="disabled"
                    >
                        <span
                            wire:loading.remove
                            wire:target="syncFromGitHub"
                        >Sync from GitHub</span>
                        <span
                            wire:loading
                            wire:target="syncFromGitHub"
                        >Syncing...</span>
                    </flux:button>
                </div>
            </div>

            {{-- Filters Section --}}
            <div
                id="filters-container"
                class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="resetFilters"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear All
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {{-- Search Filter --}}
                    <div>
                        <flux:label
                            for="search"
                            class="text-xs"
                        >Search Version</flux:label>
                        <flux:input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            id="search"
                            placeholder="3.11.4"
                            size="sm"
                        />
                    </div>

                    {{-- Color Filter --}}
                    <div>
                        <flux:label
                            for="colorFilter"
                            class="text-xs"
                        >Color</flux:label>
                        <flux:select
                            wire:model.live="colorFilter"
                            id="colorFilter"
                            size="sm"
                            variant="listbox"
                        >
                            <flux:select.option value="">All Colors</flux:select.option>
                            <flux:select.option value="green">Green</flux:select.option>
                            <flux:select.option value="red">Red</flux:select.option>
                            <flux:select.option value="gray">Gray</flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </div>

            {{-- Table Section --}}
            <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-800">
                            <tr>
                                <th
                                    scope="col"
                                    class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400 hover:bg-gray-700"
                                    wire:click="sortByColumn('version_major')"
                                >
                                    <div class="flex items-center gap-2">
                                        Version
                                        @if ($sortBy === 'version_major')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </div>
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Color
                                </th>
                                <th
                                    scope="col"
                                    class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400 hover:bg-gray-700"
                                    wire:click="sortByColumn('mod_count')"
                                >
                                    <div class="flex items-center gap-2">
                                        Mod Count
                                        @if ($sortBy === 'mod_count')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </div>
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Link
                                </th>
                                <th
                                    scope="col"
                                    class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400 hover:bg-gray-700"
                                    wire:click="sortByColumn('publish_date')"
                                >
                                    <div class="flex items-center gap-2">
                                        Publish Date
                                        @if ($sortBy === 'publish_date')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </div>
                                </th>
                                <th
                                    scope="col"
                                    class="cursor-pointer px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400 hover:bg-gray-700"
                                    wire:click="sortByColumn('created_at')"
                                >
                                    <div class="flex items-center gap-2">
                                        Created
                                        @if ($sortBy === 'created_at')
                                            <flux:icon.chevron-down
                                                class="{{ $sortDirection === 'desc' ? '' : 'rotate-180' }} h-3 w-3"
                                            />
                                        @endif
                                    </div>
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-900">
                            @forelse($this->versions as $version)
                                <tr class="hover:bg-gray-800">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-100">
                                        {{ $version->version }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <flux:badge
                                            :color="$version->color_class"
                                            size="sm"
                                        >
                                            {{ ucfirst($version->color_class) }}
                                        </flux:badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-300">
                                        {{ number_format($version->mod_count) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-300">
                                        @if ($version->link)
                                            <a
                                                href="{{ $version->link }}"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 text-blue-400 hover:underline"
                                            >
                                                {{ parse_url($version->link, PHP_URL_HOST) }}
                                                <flux:icon.arrow-top-right-on-square variant="micro" />
                                            </a>
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if ($version->publish_date)
                                            @if ($version->publish_date->isFuture())
                                                <div class="flex items-center gap-1">
                                                    <flux:icon.clock
                                                        variant="micro"
                                                        class="text-amber-500"
                                                    />
                                                    <span
                                                        class="text-amber-400"
                                                        title="{{ $this->toUserTimezone($version->publish_date)->format('F j, Y \a\t g:i A T') }}"
                                                    >
                                                        {{ $this->toUserTimezone($version->publish_date)->format('M j, Y H:i') }}
                                                    </span>
                                                </div>
                                            @else
                                                <span
                                                    class="text-green-400"
                                                    title="{{ $this->toUserTimezone($version->publish_date)->format('F j, Y \a\t g:i A T') }}"
                                                >
                                                    {{ $this->toUserTimezone($version->publish_date)->format('M j, Y') }}
                                                </span>
                                            @endif
                                        @else
                                            <flux:badge
                                                color="gray"
                                                size="sm"
                                            >Unpublished</flux:badge>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-300">
                                        {{ $version->created_at->format('M j, Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button
                                                wire:click="showEditVersion({{ $version->id }})"
                                                variant="ghost"
                                                size="sm"
                                                icon="pencil"
                                                square="true"
                                            />
                                            <flux:button
                                                wire:click="deleteVersion({{ $version->id }})"
                                                wire:confirm="Are you sure you want to delete this version? This action cannot be undone."
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                square="true"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="7"
                                        class="px-6 py-12 text-center"
                                    >
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon.inbox class="h-12 w-12 text-gray-600" />
                                            <p class="text-gray-400">No SPT versions found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if ($this->versions->hasPages())
                    <div class="border-t border-gray-700 px-6 py-4">
                        {{ $this->versions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Create Modal --}}
    <flux:modal
        wire:model.self="showCreateModal"
        variant="flyout"
    >
        <form wire:submit.prevent="saveVersion">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Create SPT Version</flux:heading>
                    <flux:subheading>Add a new SPT version to the system.</flux:subheading>
                </div>

                <flux:separator />

                <div class="space-y-4">
                    {{-- Version Input --}}
                    <flux:field>
                        <flux:label for="formVersion">Version</flux:label>
                        <flux:input
                            type="text"
                            wire:model.defer="formVersion"
                            id="formVersion"
                            placeholder="4.12.1"
                            required
                        />
                        <flux:error name="formVersion" />
                        <flux:description>Use semantic versioning formatting</flux:description>
                    </flux:field>

                    {{-- Link Input --}}
                    <flux:field>
                        <flux:label
                            for="formLink"
                            badge="Optional"
                        >GitHub Link</flux:label>
                        <flux:input
                            type="url"
                            wire:model.defer="formLink"
                            id="formLink"
                            placeholder="https://github.com/sp-tarkov/build/releases/tag/..."
                        />
                        <flux:error name="formLink" />
                    </flux:field>

                    {{-- Color Class Select --}}
                    <flux:field>
                        <flux:label for="formColorClass">Color Class</flux:label>
                        <flux:select
                            variant="listbox"
                            wire:model.defer="formColorClass"
                            id="formColorClass"
                            required
                        >
                            <flux:select.option value="red">Red</flux:select.option>
                            <flux:select.option value="orange">Orange</flux:select.option>
                            <flux:select.option value="amber">Amber</flux:select.option>
                            <flux:select.option value="yellow">Yellow</flux:select.option>
                            <flux:select.option value="lime">Lime</flux:select.option>
                            <flux:select.option value="green">Green</flux:select.option>
                            <flux:select.option value="emerald">Emerald</flux:select.option>
                            <flux:select.option value="teal">Teal</flux:select.option>
                            <flux:select.option value="cyan">Cyan</flux:select.option>
                            <flux:select.option value="sky">Sky</flux:select.option>
                            <flux:select.option value="blue">Blue</flux:select.option>
                            <flux:select.option value="indigo">Indigo</flux:select.option>
                            <flux:select.option value="violet">Violet</flux:select.option>
                            <flux:select.option value="purple">Purple</flux:select.option>
                            <flux:select.option value="fuchsia">Fuchsia</flux:select.option>
                            <flux:select.option value="pink">Pink</flux:select.option>
                            <flux:select.option value="rose">Rose</flux:select.option>
                            <flux:select.option value="slate">Slate</flux:select.option>
                            <flux:select.option value="gray">Gray</flux:select.option>
                            <flux:select.option value="zinc">Zinc</flux:select.option>
                            <flux:select.option value="neutral">Neutral</flux:select.option>
                            <flux:select.option value="stone">Stone</flux:select.option>
                        </flux:select>
                        <flux:error name="formColorClass" />
                    </flux:field>

                    {{-- Publish Date Input --}}
                    <flux:field>
                        <flux:label
                            for="formPublishDate"
                            badge="Optional"
                        >Publish Date</flux:label>
                        <flux:input
                            type="datetime-local"
                            wire:model.defer="formPublishDate"
                            id="formPublishDate"
                        />
                        <flux:error name="formPublishDate" />
                        <flux:description>Leave empty to keep unpublished. Set a past date to publish immediately, or
                            future date to schedule. Time is in your local timezone
                            ({{ auth()->user()->timezone ?? 'UTC' }}).</flux:description>
                    </flux:field>
                </div>

                <flux:separator />

                <div class="flex gap-2">
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                    >
                        <span
                            wire:loading.remove
                            wire:target="saveVersion"
                        >Create</span>
                        <span
                            wire:loading
                            wire:target="saveVersion"
                        >Saving...</span>
                    </flux:button>
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="closeModals"
                    >Cancel</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal
        wire:model.self="showEditModal"
        variant="flyout"
    >
        <form wire:submit.prevent="saveVersion">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Edit SPT Version</flux:heading>
                    <flux:subheading>Modify the SPT version details below.</flux:subheading>
                </div>

                <flux:separator />

                <div class="space-y-4">
                    {{-- Version Input --}}
                    <flux:field>
                        <flux:label for="formVersionEdit">Version</flux:label>
                        <flux:input
                            type="text"
                            wire:model.defer="formVersion"
                            id="formVersionEdit"
                            placeholder="4.12.1"
                            required
                        />
                        <flux:error name="formVersion" />
                        <flux:description>Use semantic versioning formatting</flux:description>
                    </flux:field>

                    {{-- Link Input --}}
                    <flux:field>
                        <flux:label
                            for="formLinkEdit"
                            badge="Optional"
                        >GitHub Link</flux:label>
                        <flux:input
                            type="url"
                            wire:model.defer="formLink"
                            id="formLinkEdit"
                            placeholder="https://github.com/sp-tarkov/build/releases/tag/..."
                        />
                        <flux:error name="formLink" />
                    </flux:field>

                    {{-- Color Class Select --}}
                    <flux:field>
                        <flux:label for="formColorClassEdit">Color Class</flux:label>
                        <flux:select
                            variant="listbox"
                            wire:model.defer="formColorClass"
                            id="formColorClassEdit"
                            required
                        >
                            <flux:select.option value="red">Red</flux:select.option>
                            <flux:select.option value="orange">Orange</flux:select.option>
                            <flux:select.option value="amber">Amber</flux:select.option>
                            <flux:select.option value="yellow">Yellow</flux:select.option>
                            <flux:select.option value="lime">Lime</flux:select.option>
                            <flux:select.option value="green">Green</flux:select.option>
                            <flux:select.option value="emerald">Emerald</flux:select.option>
                            <flux:select.option value="teal">Teal</flux:select.option>
                            <flux:select.option value="cyan">Cyan</flux:select.option>
                            <flux:select.option value="sky">Sky</flux:select.option>
                            <flux:select.option value="blue">Blue</flux:select.option>
                            <flux:select.option value="indigo">Indigo</flux:select.option>
                            <flux:select.option value="violet">Violet</flux:select.option>
                            <flux:select.option value="purple">Purple</flux:select.option>
                            <flux:select.option value="fuchsia">Fuchsia</flux:select.option>
                            <flux:select.option value="pink">Pink</flux:select.option>
                            <flux:select.option value="rose">Rose</flux:select.option>
                            <flux:select.option value="slate">Slate</flux:select.option>
                            <flux:select.option value="gray">Gray</flux:select.option>
                            <flux:select.option value="zinc">Zinc</flux:select.option>
                            <flux:select.option value="neutral">Neutral</flux:select.option>
                            <flux:select.option value="stone">Stone</flux:select.option>
                        </flux:select>
                        <flux:error name="formColorClass" />
                    </flux:field>

                    {{-- Publish Date Input --}}
                    <flux:field>
                        <flux:label
                            for="formPublishDateEdit"
                            badge="Optional"
                        >Publish Date</flux:label>
                        <flux:input
                            type="datetime-local"
                            wire:model.defer="formPublishDate"
                            id="formPublishDateEdit"
                        />
                        <flux:error name="formPublishDate" />
                        <flux:description>Leave empty to keep unpublished. Set a past date to publish immediately, or
                            future date to schedule. Time is in your local timezone
                            ({{ auth()->user()->timezone ?? 'UTC' }}).</flux:description>
                    </flux:field>
                </div>

                <flux:separator />

                <div class="flex gap-2">
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                    >
                        <span
                            wire:loading.remove
                            wire:target="saveVersion"
                        >Update</span>
                        <span
                            wire:loading
                            wire:target="saveVersion"
                        >Saving...</span>
                    </flux:button>
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="closeModals"
                    >Cancel</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>
