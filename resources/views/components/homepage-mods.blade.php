<div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
    <x-page-content-title :title="$featured['title']" :button-text="__('View All')" :button-link="$featured['link']" />
    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($featured['mods'] as $mod)
            <x-mod-card :mod="$mod" :version="$mod->latestVersion" />
        @endforeach
    </div>

    <x-page-content-title :title="$latest['title']" :button-text="__('View All')" :button-link="$latest['link']" />
    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($latest['mods'] as $mod)
            <x-mod-card :mod="$mod" :version="$mod->latestVersion" />
        @endforeach
    </div>

    <x-page-content-title :title="$updated['title']" :button-text="__('View All')" :button-link="$updated['link']" />
    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($updated['mods'] as $mod)
            <x-mod-card :mod="$mod" :version="$mod->lastUpdatedVersion" />
        @endforeach
    </div>
</div>
