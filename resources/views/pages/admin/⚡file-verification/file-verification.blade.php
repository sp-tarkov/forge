<div>
    <x-slot name="header">
        <div class="flex items-center justify-between w-full">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
                    {{ __('File Verification') }}
                </h2>
            </div>
        </div>
    </x-slot>

    <div class="px-6 lg:px-8">
        <div class="space-y-6">
            {{-- Filters Section --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filters</h3>
                    <flux:button
                        wire:click="$set('statusFilter', '')"
                        variant="outline"
                        size="sm"
                        icon="x-mark"
                    >
                        Clear
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <flux:label for="statusFilter" class="text-xs">Status</flux:label>
                        <flux:select wire:model.live="statusFilter" id="statusFilter" size="sm" variant="listbox">
                            <flux:select.option value="">All Statuses</flux:select.option>
                            @foreach(\App\Enums\VerificationStatus::cases() as $status)
                                <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </div>

            {{-- Table Section --}}
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Type
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Trigger
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Download
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Archive
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Files
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Date
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($this->results as $result)
                                <tr
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
                                    wire:click="showDetails({{ $result->id }})"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        <flux:badge color="gray" size="sm">{{ $this->getVerifiableType($result) }}</flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $this->getVerifiableName($result) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <flux:badge :color="$result->status->color()" size="sm">
                                            {{ $result->status->label() }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $result->trigger->label() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($result->download_ok === true)
                                            <flux:icon.check-circle variant="micro" class="text-green-500" />
                                        @elseif($result->download_ok === false)
                                            <flux:icon.x-circle variant="micro" class="text-red-500" />
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($result->archive_ok === true)
                                            <flux:icon.check-circle variant="micro" class="text-green-500" />
                                        @elseif($result->archive_ok === false)
                                            <flux:icon.x-circle variant="micro" class="text-red-500" />
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        @if($result->file_tree)
                                            {{ count($result->file_tree) }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ $result->created_at->format('M j, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" wire:click.stop>
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
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon.inbox class="w-12 h-12 text-gray-400 dark:text-gray-600" />
                                            <p class="text-gray-500 dark:text-gray-400">No verification results found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($this->results->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $this->results->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Detail Modal --}}
    <flux:modal wire:model.self="showDetailModal" variant="flyout" class="max-w-2xl">
        @if($this->selectedResult)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Verification Details</flux:heading>
                    <flux:subheading>{{ $this->getVerifiableName($this->selectedResult) }}</flux:subheading>
                </div>

                <flux:separator />

                {{-- Status & Metadata --}}
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Status</span>
                        <div class="mt-1">
                            <flux:badge :color="$this->selectedResult->status->color()">
                                {{ $this->selectedResult->status->label() }}
                            </flux:badge>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Trigger</span>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->selectedResult->trigger->label() }}</p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Started</span>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ $this->selectedResult->started_at?->format('M j, Y H:i:s') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Completed</span>
                        <p class="mt-1 text-gray-900 dark:text-gray-100">
                            {{ $this->selectedResult->completed_at?->format('M j, Y H:i:s') ?? '—' }}
                        </p>
                    </div>
                </div>

                {{-- Download URL --}}
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Download URL</span>
                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100 break-all">
                        {{ $this->selectedResult->download_url }}
                    </p>
                </div>

                {{-- Download Details --}}
                @if($this->selectedResult->download_ok !== null)
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Download</span>
                            <div class="mt-1">
                                @if($this->selectedResult->download_ok)
                                    <flux:badge color="green" size="sm">OK</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">Failed</flux:badge>
                                @endif
                            </div>
                        </div>
                        @if($this->selectedResult->downloaded_size)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">File Size</span>
                                <p class="mt-1 text-gray-900 dark:text-gray-100">
                                    {{ \Illuminate\Support\Number::fileSize($this->selectedResult->downloaded_size, precision: 2) }}
                                </p>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- SHA-256 --}}
                @if($this->selectedResult->downloaded_sha256)
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">SHA-256</span>
                        <p class="mt-1 text-xs font-mono text-gray-900 dark:text-gray-100 break-all">
                            {{ $this->selectedResult->downloaded_sha256 }}
                        </p>
                    </div>
                @endif

                {{-- Failure Reason --}}
                @if($this->selectedResult->failure_reason)
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Failure Reason</span>
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ $this->selectedResult->failure_reason }}
                        </p>
                    </div>
                @endif

                {{-- File Tree --}}
                @if($this->selectedResult->file_tree && count($this->selectedResult->file_tree) > 0)
                    <div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            File Tree ({{ count($this->selectedResult->file_tree) }} files)
                        </span>
                        <div class="mt-2 max-h-64 overflow-y-auto bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <ul class="space-y-0.5">
                                @foreach($this->selectedResult->file_tree as $file)
                                    <li class="text-xs font-mono text-gray-700 dark:text-gray-300">{{ $file }}</li>
                                @endforeach
                            </ul>
                        </div>
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
