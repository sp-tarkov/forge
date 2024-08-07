<x-app-layout>
    {{--
    TODO:
    [ ] search bar for mods
    [ ] mods section filter
    [ ] spt version filter
    [ ] tags filter
    [ ] small / mobile display handling
    [ ] light mode theme handling
    --}}

    {{-- page links --}}
    <div class="m-6">
        {{ $mods->links() }}
    </div>

    {{-- 2 column grid layout --}}
    <div class="grid gap-6 grid-cols-[1fr_auto]">

        {{-- search / section filters, mods --}}
        <div>
            {{-- mods serach bar --}}
            <div>
                <p class="text-gray-700 dark:text-gray-200">--SEARCH BAR--</p>
            </div>
            {{-- section filters --}}
            <div>
                <p class="text-gray-700 dark:text-gray-200">--SECTION FILTERS--</p>
            </div>

            {{-- mod cards --}}
            <div class="grid gap-6 grid-cols-2">
                @foreach($mods as $mod)
                    <x-mod-card :mod="$mod" />
                @endforeach
            </div>
        </div>

        {{-- version filters, tags --}}
        <div class="max-w-sm">
            {{-- spt version filters --}}
            <div>
                <p class="text-gray-700 dark:text-gray-200">--SPT VERSION FILTER--</p>
            </div>

            {{-- tag filters --}}
            <div>
                <p class="text-gray-700 dark:text-gray-200">--TAG FILTER HERE--</p>
            </div>
        </div>
    </div>
    {{-- page links --}}
    <div class="m-6">
        {{ $mods->links() }}
    </div>


</x-app-layout>
