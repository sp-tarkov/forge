<x-app-layout>

    @foreach($mods as $mod)
        <p>{{$mod->name}}</p>
    @endforeach

</x-app-layout>
