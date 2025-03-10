<div class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
    <x-page-content-title :title="__('Featured Mods')" :button-text="__('View All')" button-link="/mods?featured=only" />
    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($this->featured as $mod)
            <div wire:key="mod-card-featured-{{ $mod->id }}">
                <x-mod-card :mod="$mod" :version="$mod->latestVersion" section="featured" :is-home-page="true" />
            </div>
        @endforeach
    </div>

    <x-page-content-title :title="__('Newest Mods')" :button-text="__('View All')" button-link="/mods" />
    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($this->latest as $mod)
            <div wire:key="mod-card-latest-{{ $mod->id }}">
                <x-mod-card :mod="$mod" :version="$mod->latestVersion" section="latest" />
            </div>
        @endforeach
    </div>

    <x-page-content-title :title="__('Recently Updated Mods')" :button-text="__('View All')" button-link="/mods?order=updated" />
    <div class="my-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($this->updated as $mod)
            <div wire:key="mod-card-updated-{{ $mod->id }}">
                <x-mod-card :mod="$mod" :version="$mod->latestUpdatedVersion" section="updated" />
            </div>
        @endforeach
    </div>
</div>
