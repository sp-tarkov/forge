@placeholder
    <div
        id="lists"
        class="space-y-4"
        aria-busy="true"
    >
        <span class="sr-only">{{ __('Loading lists…') }}</span>
        <flux:skeleton class="h-10 w-64 rounded" />
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @for ($i = 0; $i < 3; $i++)
                <div class="p-4 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
                    <flux:skeleton.group class="space-y-3">
                        <flux:skeleton class="h-5 w-3/4 rounded" />
                        <flux:skeleton class="h-4 w-1/2 rounded" />
                        <flux:skeleton class="h-4 w-full rounded" />
                    </flux:skeleton.group>
                </div>
            @endfor
        </div>
    </div>
@endplaceholder

<div id="lists">
    @if ($this->isOwner)
        <div class="mb-4 flex items-center justify-between">
            <div class="text-sm text-gray-400">
                {{ __(':count of :max lists', ['count' => $this->listCount, 'max' => config('mod-lists.max_lists_per_user')]) }}
            </div>
            <flux:button
                variant="primary"
                size="sm"
                icon="plus"
                :href="route('list.create')"
                wire:navigate
            >
                {{ __('New list') }}
            </flux:button>
        </div>
    @endif

    @if ($this->lists->total() > 0)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->lists as $list)
                <x-list.card
                    wire:key="user-lists-card-{{ $list->id }}"
                    :list="$list"
                    :show-owner="false"
                />
            @endforeach
        </div>
        <div class="mt-4">
            {{ $this->lists->links() }}
        </div>
    @else
        <div class="p-4 sm:p-6 bg-gray-950 rounded-xl shadow-md shadow-gray-950 drop-shadow-2xl">
            <div class="text-center py-8">
                <flux:icon.list-bullet class="mx-auto size-12 text-gray-400" />
                <h2 class="mt-2 text-sm font-semibold text-gray-100">
                    {{ __('No lists yet') }}
                </h2>
                <p class="mt-1 text-sm text-gray-400">
                    @if ($this->isOwner)
                        {{ __('Create a list to start organizing your favourite mods.') }}
                    @else
                        {{ __('This user has not created any public lists yet.') }}
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>
