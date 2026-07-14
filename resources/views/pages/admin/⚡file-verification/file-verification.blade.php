<div>
    <x-slot name="header">
        <div class="flex w-full items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-200">
                    {{ __('File Verification') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        <div class="space-y-6">
            {{-- Actions --}}
            <div class="flex justify-end">
                <flux:button
                    wire:click="openQueueModal"
                    variant="primary"
                    icon="plus"
                >
                    Queue Verification
                </flux:button>
            </div>

            {{-- Filters Section --}}
            <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="$set('statusFilter', '')"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <flux:label
                            for="statusFilter"
                            class="text-xs"
                        >Status</flux:label>
                        <flux:select
                            wire:model.live="statusFilter"
                            id="statusFilter"
                            size="sm"
                            variant="listbox"
                        >
                            <flux:select.option value="">All Statuses</flux:select.option>
                            @foreach (\App\Enums\VerificationStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}
                                </flux:select.option>
                            @endforeach
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
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Type
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Name
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Trigger
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Download
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Archive
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Files
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-400"
                                >
                                    Date
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
                            @forelse($this->results as $result)
                                <tr
                                    class="cursor-pointer hover:bg-gray-800"
                                    wire:click="showDetails({{ $result->id }})"
                                >
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-300">
                                        <flux:badge
                                            color="gray"
                                            size="sm"
                                        >{{ $this->getVerifiableType($result) }}</flux:badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-100">
                                        {{ $this->getVerifiableName($result) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <flux:badge
                                            :color="$result->status->color()"
                                            size="sm"
                                        >
                                            {{ $result->status->label() }}
                                        </flux:badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-300">
                                        {{ $result->trigger->label() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if ($result->download_ok === true)
                                            <flux:icon.check-circle
                                                variant="micro"
                                                class="text-green-500"
                                            />
                                        @elseif($result->download_ok === false)
                                            <flux:icon.x-circle
                                                variant="micro"
                                                class="text-red-500"
                                            />
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if ($result->archive_ok === true)
                                            <flux:icon.check-circle
                                                variant="micro"
                                                class="text-green-500"
                                            />
                                        @elseif($result->archive_ok === false)
                                            <flux:icon.x-circle
                                                variant="micro"
                                                class="text-red-500"
                                            />
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-300">
                                        @if ($result->file_tree)
                                            {{ count($result->file_tree) }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-300">
                                        {{ $result->created_at->format('M j, Y H:i') }}
                                    </td>
                                    <td
                                        class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium"
                                        wire:click.stop
                                    >
                                        <flux:button
                                            wire:click="reverify({{ $result->id }})"
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-path"
                                            square="true"
                                            title="Re-verify"
                                        />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="9"
                                        class="px-6 py-12 text-center"
                                    >
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon.inbox class="h-12 w-12 text-gray-600" />
                                            <p class="text-gray-400">No verification results found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->results->hasPages())
                    <div class="border-t border-gray-700 px-6 py-4">
                        {{ $this->results->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Queue Verification Modal --}}
    <flux:modal
        wire:model.self="showQueueModal"
        class="w-full max-w-lg"
    >
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Queue Verification</flux:heading>
                <flux:subheading>Select a mod, then choose which version to verify.</flux:subheading>
            </div>

            @if ($this->queueSelectedMod === null)
                <flux:input
                    wire:model.live.debounce.300ms="queueSearch"
                    placeholder="Search mods by name..."
                    icon="magnifying-glass"
                />

                @if (mb_strlen(mb_trim($queueSearch)) < 2)
                    <p class="text-sm text-gray-400">Type at least two characters to search.</p>
                @elseif ($this->queueModResults->isEmpty())
                    <p class="text-sm text-gray-400">No mods with downloadable versions match the search.</p>
                @else
                    <div class="space-y-2">
                        @foreach ($this->queueModResults as $mod)
                            <button
                                type="button"
                                wire:key="queue-mod-{{ $mod->id }}"
                                wire:click="selectQueueMod({{ $mod->id }})"
                                class="flex w-full items-center justify-between gap-3 rounded-lg border border-gray-700 bg-gray-800 p-3 text-left hover:border-gray-500"
                            >
                                <span class="truncate text-sm font-medium text-gray-100">{{ $mod->name }}</span>
                                <flux:icon.chevron-right
                                    variant="micro"
                                    class="text-gray-400"
                                />
                            </button>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-700 bg-gray-800 p-3">
                    <span class="truncate text-sm font-medium text-gray-100">{{ $this->queueSelectedMod->name }}</span>
                    <flux:button
                        wire:click="clearQueueMod"
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                        square="true"
                        title="Change mod"
                    />
                </div>

                @if ($this->queueModVersions->isEmpty())
                    <p class="text-sm text-gray-400">This mod has no versions with a download link.</p>
                @else
                    <div>
                        <flux:label
                            for="queueModVersionId"
                            class="text-xs"
                        >Version</flux:label>
                        <flux:select
                            wire:model.live="queueModVersionId"
                            id="queueModVersionId"
                            size="sm"
                            variant="listbox"
                            placeholder="Select a version..."
                        >
                            @foreach ($this->queueModVersions as $modVersion)
                                <flux:select.option
                                    wire:key="queue-mod-version-{{ $modVersion->id }}"
                                    value="{{ $modVersion->id }}"
                                >
                                    v{{ $modVersion->version }}
                                    ({{ $modVersion->verification_status?->label() ?? 'Unverified' }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
            @endif

            <flux:separator />

            <div class="flex gap-2">
                <flux:button
                    wire:click="queueSelectedVersion"
                    variant="primary"
                    icon="plus"
                    :disabled="$queueModVersionId === null"
                >
                    Queue Verification
                </flux:button>
                <flux:button
                    wire:click="closeQueueModal"
                    variant="ghost"
                >
                    Close
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Detail Modal --}}
    <flux:modal
        wire:model.self="showDetailModal"
        variant="flyout"
        class="max-w-2xl"
    >
        @if ($this->selectedResult)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Verification Details</flux:heading>
                    <flux:subheading>{{ $this->getVerifiableName($this->selectedResult) }}</flux:subheading>
                </div>

                <flux:separator />

                {{-- Status & Metadata --}}
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-400">Status</span>
                        <div class="mt-1">
                            <flux:badge :color="$this->selectedResult->status->color()">
                                {{ $this->selectedResult->status->label() }}
                            </flux:badge>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-400">Trigger</span>
                        <p class="mt-1 text-gray-100">{{ $this->selectedResult->trigger->label() }}</p>
                    </div>
                    <div>
                        <span class="text-gray-400">Started</span>
                        <p class="mt-1 text-gray-100">
                            {{ $this->selectedResult->started_at?->format('M j, Y H:i:s') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-400">Completed</span>
                        <p class="mt-1 text-gray-100">
                            {{ $this->selectedResult->completed_at?->format('M j, Y H:i:s') ?? '—' }}
                        </p>
                    </div>
                </div>

                {{-- Download URL --}}
                <div>
                    <span class="text-sm text-gray-400">Download URL</span>
                    <p class="mt-1 break-all text-sm text-gray-100">
                        {{ $this->selectedResult->download_url }}
                    </p>
                </div>

                {{-- Download Details --}}
                @if ($this->selectedResult->download_ok !== null)
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-400">Download</span>
                            <div class="mt-1">
                                @if ($this->selectedResult->download_ok)
                                    <flux:badge
                                        color="green"
                                        size="sm"
                                    >OK</flux:badge>
                                @else
                                    <flux:badge
                                        color="red"
                                        size="sm"
                                    >Failed</flux:badge>
                                @endif
                            </div>
                        </div>
                        @if ($this->selectedResult->downloaded_size)
                            <div>
                                <span class="text-gray-400">File Size</span>
                                <p class="mt-1 text-gray-100">
                                    {{ \Illuminate\Support\Number::fileSize($this->selectedResult->downloaded_size, precision: 2) }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- SHA-256 --}}
                @if ($this->selectedResult->downloaded_sha256)
                    <div>
                        <span class="text-sm text-gray-400">SHA-256</span>
                        <p class="mt-1 break-all font-mono text-xs text-gray-100">
                            {{ $this->selectedResult->downloaded_sha256 }}
                        </p>
                    </div>
                @endif

                {{-- Failure Reason --}}
                @if ($this->selectedResult->failure_reason)
                    <div>
                        <span class="text-sm text-gray-400">Failure Reason</span>
                        <p class="mt-1 text-sm text-red-400">
                            {{ $this->selectedResult->failure_reason }}
                        </p>
                    </div>
                @endif

                {{-- Checks --}}
                <x-verification.check-list :checks="$this->selectedChecks" />

                {{-- File Tree --}}
                @if ($this->selectedFileCount > 0)
                    <div>
                        <span class="text-sm text-gray-400">
                            File Tree
                            ({{ Number::format($this->selectedFileCount) }}
                            {{ Str::plural('file', $this->selectedFileCount) }})
                        </span>
                        <x-verification.file-tree
                            :nodes="$this->selectedFileTree"
                            class="mt-2"
                        />
                        @if ($this->selectedHiddenFileCount > 0)
                            <p class="mt-1 text-xs text-gray-500">
                                {{ Number::format($this->selectedHiddenFileCount) }} more files not shown
                            </p>
                        @endif
                    </div>
                @endif

                <flux:separator />

                <div class="flex gap-2">
                    <flux:button
                        wire:click="reverify({{ $this->selectedResult->id }})"
                        variant="primary"
                        icon="arrow-path"
                    >
                        Re-verify
                    </flux:button>
                    <flux:button
                        wire:click="closeModal"
                        variant="ghost"
                    >
                        Close
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
