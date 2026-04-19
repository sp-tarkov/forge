<div>
    @auth
        <flux:tooltip :content="$this->isFavourited ? __('Remove from Favourites') : __('Add to Favourites')">
            <flux:button
                wire:click="toggle"
                variant="subtle"
                :size="$size"
                square
                icon="heart"
                :class="$this->isFavourited ? 'text-rose-500 dark:text-rose-400' : 'text-gray-500 dark:text-gray-400'"
                aria-label="{{ $this->isFavourited ? __('Remove from Favourites') : __('Add to Favourites') }}"
            />
        </flux:tooltip>
    @else
        <flux:tooltip :content="__('Log in to favourite')">
            <flux:button
                :href="route('login')"
                variant="subtle"
                :size="$size"
                square
                icon="heart"
                class="text-gray-400"
            />
        </flux:tooltip>
    @endauth
</div>
