<div>
    <form wire:submit="save" class="space-y-4 m-6">
        @csrf

        <flux:field>
            <flux:label>{{ __('Avatar') }}</flux:label>
            <flux:input type="file" wire:model="modAvatar" accept="image/*" />
            <flux:error name="modAvatar" />
            <div wire:loading wire:target="modAvatar" class="mt-2">
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-cyan-500 h-2.5 rounded-full" style="width: 0%" wire:loading.class="animate-pulse"></div>
                </div>
            </div>
            @if ($modAvatar)
                <div class="mt-2">
                    <img src="{{ $modAvatar->temporaryUrl() }}" class="h-20 w-20 object-cover rounded" alt="Avatar preview">
                </div>
            @endif
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Name') }}</flux:label>
            <flux:input type="text" wire:model="modName" />
            <flux:error name="modName" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Version') }}</flux:label>
            <flux:input type="text" wire:model="modVersion" />
            <flux:error name="modVersion" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('SPT Version Constraint') }}</flux:label>
            <flux:input type="text" wire:model="modSptVersionConstraint" />
            <flux:error name="modSptVersionConstraint" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Teaser') }}</flux:label>
            <flux:input type="text" wire:model="modTeaser" />
            <flux:error name="modTeaser" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Description') }}</flux:label>
            <flux:textarea rows="5" wire:model="modDescription" />
            <flux:error name="modDescription" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Source Code Url') }}</flux:label>
            <flux:input type="text" wire:model="modSourceCodeUrl" />
            <flux:error name="modSourceCodeUrl" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod External Url') }}</flux:label>
            <flux:input type="text" wire:model="modExternalUrl" />
            <flux:error name="modExternalUrl" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod VirusTotal Url') }}</flux:label>
            <flux:input type="text" wire:model="modVirusTotalUrl" />
            <flux:error name="modVirusTotalUrl" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Mod Publish Date') }}</flux:label>
            <flux:input type="date" wire:model="modPublishDate"/>
            <flux:error name="modPublishDate" />
        </flux:field>

        <div class="flex space-x-4">
            <flux:button variant="primary" class="text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">{{ __('Create Mod') }}</flux:button>
            <flux:button wire:click="cancel">{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
