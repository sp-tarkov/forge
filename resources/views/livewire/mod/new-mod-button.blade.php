<div>
    @if(auth()->check() && auth()->user()->hasMFAEnabled())
        <flux:button wire:click="newMod">{{ __('Create a New Mod') }}</flux:button>
    @else
        <flux:tooltip content="You must be logged in and have MFA enabled to create a new mod">
            <div>
                <flux:button disabled="true">{{ __('New Mod') }}</flux:button>
            </div>
        </flux:tooltip>
    @endif
</div>
