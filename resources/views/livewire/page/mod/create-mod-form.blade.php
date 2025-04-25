<div>
    <form wire:submit="save" class="space-y-4 m-6">
        @csrf
{{--        requires a trait to be added for file uploads? --}}
{{--        <flux:field>--}}
{{--            <flux:label>{{ __('Avatar') }}</flux:label>--}}
{{--            <flux:input type="file" wire:model="modAvatar" />--}}
{{--        </flux:field>--}}

        <flux:field>
            <flux:label>{{ __('Mod Name') }}</flux:label>
            <flux:input required type="text" wire:model="modName" />
            <flux:error name="modName" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Version') }}</flux:label>
            <flux:input type="text" wire:model="modVersion" />
            <flux:error name="modVersion" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Teaser') }}</flux:label>
            <flux:input type="text" wire:model="modTeaser" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Description') }}</flux:label>
            <flux:input type="text" wire:model="modDescription" />
        </flux:field>


{{--        <flux:input type="date" wire:model="publishDate"/>--}}

        <flux:button variant="primary" class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">{{ __('Create Mod') }}</flux:button>
    </form>
</div>
