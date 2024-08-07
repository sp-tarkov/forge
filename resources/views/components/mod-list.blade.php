@props(['mods', 'versionScope'])

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    @foreach ($mods as $mod)
        <x-mod-card :mod="$mod" :versionScope="$versionScope"/>
    @endforeach
</div>
