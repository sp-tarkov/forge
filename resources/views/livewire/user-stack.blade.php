<div class="w-full text-gray-600 bg-white shadow-md dark:text-gray-200 dark:bg-gray-900 rounded-xl p-4 m-4">
    <div class="flex justify-center items-center">
        <h2 class="text-2xl">{{$label}}</h2>
    </div>
    <div class="flex ml-6 py-2">
        @foreach($users->slice(0, $limit) as $user)
            <div class="relative group">
                <a href="{{$user->profileUrl()}}" class="rounded-full -ml-6 z-20 bg-[#ebf4ff] h-16 w-16 flex justify-center items-center border">
                    <img class="h-full w-full rounded-full" src="{{$user->profile_photo_url}}"
                         alt="{{$user->name[0]}}" />
                </a>
                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded shadow-lg opacity-0 group-hover:opacity-100">
                    {{$user->name}}
                </div>
            </div>
        @endforeach
        @if($users->count() > $limit)
            <div class="relative group">
                <span class="rounded-full -ml-6 z-20 bg-cyan-500 dark:bg-cyan-700 h-16 w-16 flex justify-center items-center border text-white">+{{$users->count()-$limit}}</span>
                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-max px-2 py-1 text-sm text-white bg-gray-700 rounded shadow-lg opacity-0 group-hover:opacity-100">
                    {{$users->count()}} total
                </div>
            </div>
        @endif
    </div>
    @if($users->count() > $limit)
        <div class="flex justify-center items-center">
            <button wire:click="$set('viewAll', false)">view all</button>
        </div>
    @endif

    <x-dialog-modal wire:model.live="viewAll">
        <x-slot name="title">
            <h2 class="text-2xl">testing</h2>
        </x-slot>
        <x-slot name="content">
            <p>no u</p>
        </x-slot>

        <x-slot name="footer">
            <x-button wire:click="$set('viewAll', false)">
                {{__('Close')}}
            </x-button>
        </x-slot>

    </x-dialog-modal>
</div>
