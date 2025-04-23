<div>
    <form wire:submit="save">
        {{-- placeholder, try to use flux --}}
        <input type="text" wire:model="title">

        {{-- placeholder, try to use flux --}}
        <input type="text" wire:model="content">

        <button type="submit">Save</button>
    </form>
</div>
