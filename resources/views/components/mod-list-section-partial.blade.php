@props(['mods', 'versionScope', 'title'])

<div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
    {{--
        TODO: The button-link should be dynamic based on the versionScope. Eg. Featured `View All` button should take
              the user to the mods page with the `featured` query parameter set.
    --}}
    <x-page-content-title :title="$title" button-text="View All" button-link="/mods" />
    <x-mod-list :mods="$mods" :versionScope="$versionScope" />
</div>
