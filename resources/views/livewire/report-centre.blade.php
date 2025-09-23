<div wire:poll.10s="$refresh" class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 id="reports" class="text-lg font-semibold text-gray-900 dark:text-white">Report Centre</h3>
            @if($this->pendingReportsCount > 0)
                <flux:badge color="yellow" size="sm">
                    {{ $this->pendingReportsCount }} {{ $this->pendingReportsCount === 1 ? 'Pending Report' : 'Pending Reports' }}
                </flux:badge>
            @else
                <flux:badge color="gray" size="sm">No Pending Reports</flux:badge>
            @endif
        </div>

        @if($this->reports->count() > 0)
            <div class="space-y-4">
                @foreach($this->reports as $report)
                    <div class="group relative bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200 overflow-hidden">
                        {{-- Status indicator bar --}}
                        <div class="absolute inset-y-0 left-0 w-1 bg-{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'yellow-400' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'green-400' : 'gray-400') }}"></div>

                        <div class="p-4 pl-6">
                            {{-- Main content layout --}}
                            <div class="flex flex-col lg:flex-row lg:space-x-4 space-y-4 lg:space-y-0">
                                {{-- Left side: Report details --}}
                                <div class="flex-1">
                                    {{-- Header with reporter and status --}}
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <flux:avatar
                                                    circle="circle"
                                                    src="{{ $report->reporter->profile_photo_url }}"
                                                    name="{{ $report->reporter->name }}"
                                                    color="auto"
                                                    color:seed="{{ $report->reporter->id }}"
                                                    size="sm"
                                                />
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <span class="capitalize">{{ $report->reporter->display_name ?? $report->reporter->name }}</span> reports <span class="text-red-600 dark:text-red-400 lowercase">{{ $report->reason->label() }}</span>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $report->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>

                                        <flux:badge
                                            color="{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'yellow' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'green' : 'gray') }}"
                                            size="sm"
                                            icon="{{ $report->status === \App\Enums\ReportStatus::PENDING ? 'clock' : ($report->status === \App\Enums\ReportStatus::RESOLVED ? 'check-circle' : 'x-circle') }}"
                                        >
                                            {{ $report->status->label() }}
                                        </flux:badge>
                                    </div>

                                    {{-- Report details --}}
                                    <div class="space-y-2 lg:mb-3">

                                        @if($report->description)
                                            <div class="flex items-start space-x-2">
                                                <flux:icon.chat-bubble-left-ellipsis class="size-4 text-blue-500 mt-0.5 flex-shrink-0" />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Reason:</span>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $report->description }}</p>
                                                </div>
                                            </div>
                                        @endif

                                        @if($report->context)
                                            <div class="flex items-start space-x-2">
                                                <flux:icon.information-circle class="size-4 text-amber-500 mt-1.5 flex-shrink-0" />
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Additional Context:</span>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $report->context }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Action links - Desktop only --}}
                                    <div class="hidden lg:flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                                        <div class="flex items-center space-x-4">
                                            @if($report->reportable && method_exists($report->reportable, 'getReportableUrl'))
                                                <a href="{{ $report->reportable->getReportableUrl() }}"
                                                   target="_blank"
                                                   class="inline-flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-150">
                                                    <flux:icon.link class="size-4" />
                                                    <span>View Content</span>
                                                </a>
                                            @endif
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            @if($report->status === \App\Enums\ReportStatus::PENDING)
                                                <button
                                                    wire:click="markAsResolved({{ $report->id }})"
                                                    class="inline-flex items-center space-x-1 text-xs text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.check class="size-4" />
                                                    <span>Resolve</span>
                                                </button>

                                                <button
                                                    wire:click="markAsDismissed({{ $report->id }})"
                                                    class="inline-flex items-center space-x-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.x-mark class="size-4" />
                                                    <span>Dismiss</span>
                                                </button>
                                            @endif

                                            @can('delete', $report)
                                                <button
                                                    wire:click="deleteReport({{ $report->id }})"
                                                    wire:confirm="Are you sure you want to delete this report? This action cannot be undone."
                                                    class="inline-flex items-center space-x-1 text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.trash class="size-4" />
                                                    <span>Delete</span>
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>

                                {{-- Right side: Reported content preview --}}
                                <div class="w-full lg:w-80 flex-shrink-0 lg:border-l border-t lg:border-t-0 border-gray-200 dark:border-gray-700 lg:pl-4 pt-4 lg:pt-0">
                                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                        Reported Content
                                    </div>

                                    @if($report->reportable)
                                        @if($report->reportable_type === 'App\Models\Mod')
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <flux:icon.cube class="size-4 text-blue-500" />
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Mod</span>
                                                </div>
                                                <p class="text-sm text-gray-900 dark:text-white font-medium">{{ $report->reportable->name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-3">{{ \Illuminate\Support\Str::limit($report->reportable->teaser, 120) }}</p>
                                            </div>
                                        @elseif($report->reportable_type === 'App\Models\User')
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <flux:icon.user class="size-4 text-green-500" />
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">User</span>
                                                </div>
                                                <p class="text-sm text-gray-900 dark:text-white font-medium">{{ $report->reportable->display_name ?? $report->reportable->name }}</p>
                                                @if($report->reportable->about)
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-3">{{ \Illuminate\Support\Str::limit($report->reportable->about, 120) }}</p>
                                                @endif
                                            </div>
                                        @elseif($report->reportable_type === 'App\Models\Comment')
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <flux:icon.chat-bubble-left class="size-4 text-purple-500" />
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Comment</span>
                                                </div>
                                                <p class="text-sm text-gray-900 dark:text-white font-medium">By {{ $report->reportable->user ? ($report->reportable->user->display_name ?? $report->reportable->user->name) : 'Deleted User' }}</p>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-4 prose prose-sm max-w-none">
                                                    {{ \Illuminate\Support\Str::limit(strip_tags($report->reportable->body), 150) }}
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <div class="flex items-center justify-center h-20 text-gray-400 dark:text-gray-500">
                                            <div class="text-center">
                                                <flux:icon.exclamation-triangle class="size-6 mx-auto mb-2" />
                                                <p class="text-xs">Content has been deleted</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Action links - Mobile only --}}
                                <div class="lg:hidden flex flex-col space-y-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            @if($report->reportable && method_exists($report->reportable, 'getReportableUrl'))
                                                <a href="{{ $report->reportable->getReportableUrl() }}"
                                                   target="_blank"
                                                   class="inline-flex items-center space-x-1 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors duration-150">
                                                    <flux:icon.link class="size-4" />
                                                    <span>View Content</span>
                                                </a>
                                            @endif
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            @if($report->status === \App\Enums\ReportStatus::PENDING)
                                                <button
                                                    wire:click="markAsResolved({{ $report->id }})"
                                                    class="inline-flex items-center space-x-1 text-xs text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.check class="size-4" />
                                                    <span>Resolve</span>
                                                </button>

                                                <button
                                                    wire:click="markAsDismissed({{ $report->id }})"
                                                    class="inline-flex items-center space-x-1 text-xs text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.x-mark class="size-4" />
                                                    <span>Dismiss</span>
                                                </button>
                                            @endif

                                            @can('delete', $report)
                                                <button
                                                    wire:click="deleteReport({{ $report->id }})"
                                                    wire:confirm="Are you sure you want to delete this report? This action cannot be undone."
                                                    class="inline-flex items-center space-x-1 text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors duration-150"
                                                >
                                                    <flux:icon.trash class="size-4" />
                                                    <span>Delete</span>
                                                </button>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($this->reports->count() > 10)
                <div class="mt-6">
                    {{ $this->reports->links(data: ['scrollTo' => '#reports']) }}
                </div>
            @endif
        @else
            <div class="text-center py-8">
                <flux:icon.document-magnifying-glass size="xl" class="mx-auto text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No reports</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    There are currently no reports to review.
                </p>
            </div>
        @endif
    </div>
</div>
