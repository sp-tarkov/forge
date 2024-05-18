@props(['mods'])

<div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
    <x-page-content-title :title="$title" button-text="View All" button-link="/mods" />
    <x-mod-list :mods="$mods" :versionScope="$versionScope" />
</div>
