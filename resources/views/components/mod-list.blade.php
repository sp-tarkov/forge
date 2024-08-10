@props(['mods', 'versionScope'])

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @foreach ($mods as $mod)
        @if ($mod->{$versionScope})
            <x-mod-card :mod="$mod" :versionScope="$versionScope"/>
        @endif
    @endforeach
</div>
