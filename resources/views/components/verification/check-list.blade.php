@if (count($checks) > 0)
    <div {{ $attributes }}>
        <span class="text-sm text-gray-400">{{ __('Checks') }}</span>
        <div class="mt-2 space-y-2">
            @foreach ($sortedChecks() as $check)
                <div class="flex items-start gap-3 rounded-lg border border-gray-700 bg-gray-800 p-3">
                    <flux:badge
                        :color="$check->status->color()"
                        size="sm"
                    >{{ $check->status->label() }}</flux:badge>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-gray-100">{{ $check->label() }}</span>
                            <span class="font-mono text-xs text-gray-500">{{ $check->name }}</span>
                            @if ($check->reportOnly)
                                <flux:badge
                                    color="gray"
                                    size="sm"
                                >{{ __('Report only') }}</flux:badge>
                            @endif
                        </div>
                        @if ($check->description())
                            <p class="mt-1 text-xs text-gray-400">{{ $check->description() }}</p>
                        @endif
                        @if ($check->message)
                            @if ($check->failed() && $check->description())
                                <flux:separator class="my-2" />
                            @endif
                            <p
                                class="{{ $check->failed() ? 'text-red-400' : 'text-gray-300' }} mt-1 break-words text-xs">
                                {{ $check->message }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
