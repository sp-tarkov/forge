@props(['mods', 'versionScope', 'title', 'link'])

<div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
    <x-page-content-title :title="$title" button-text="View All" :button-link="$link" />
    <x-mod-list :mods="$mods" :versionScope="$versionScope" />
</div>
