<?php

declare(strict_types=1);

use App\Models\Mod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component {
    /**
     * The mod ID.
     */
    public int $modId;

    /**
     * Mount the component.
     */
    public function mount(int $modId): void
    {
        $this->modId = $modId;
    }

    /**
     * Get the mod.
     */
    #[Computed]
    public function mod(): Mod
    {
        return Mod::query()->findOrFail($this->modId);
    }
};
?>

@placeholder
    <div
        id="comments"
        class="space-y-6"
    >
        {{-- Comment form placeholder --}}
        <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
            <flux:skeleton.group class="space-y-4">
                <flux:skeleton class="h-24 w-full rounded" />
                <div class="flex justify-end">
                    <flux:skeleton class="h-10 w-32 rounded" />
                </div>
            </flux:skeleton.group>
        </div>

        {{-- Comment placeholders --}}
        @for ($i = 0; $i < 3; $i++)
            <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
                <flux:skeleton.group class="space-y-3">
                    {{-- Comment header --}}
                    <div class="flex items-center gap-3">
                        <flux:skeleton class="h-10 w-10 rounded-full" />
                        <div class="space-y-1">
                            <flux:skeleton class="h-4 w-24 rounded" />
                            <flux:skeleton class="h-3 w-16 rounded" />
                        </div>
                    </div>

                    {{-- Comment content --}}
                    <div class="space-y-2 pl-13">
                        <flux:skeleton class="h-4 w-full rounded" />
                        <flux:skeleton class="h-4 w-5/6 rounded" />
                        <flux:skeleton class="h-4 w-2/3 rounded" />
                    </div>
                </flux:skeleton.group>
            </div>
        @endfor
    </div>
@endplaceholder

<div id="comments">
    @if ($this->mod->comments_disabled && (auth()->user()?->isModOrAdmin() || $this->mod->isAuthorOrOwner(auth()->user())))
        <div class="mb-6">
            <flux:callout
                icon="exclamation-triangle"
                color="orange"
                inline="inline"
            >
                <flux:callout.text>
                    {{ __('Comments have been disabled for this mod and are not visible to normal users. As :role, you can still view and manage all comments.', ['role' => auth()->user()?->isModOrAdmin() ? 'a staff member or moderator' : 'the mod owner or author']) }}
                </flux:callout.text>
            </flux:callout>
        </div>
    @endif
    <livewire:comment-component
        wire:key="comment-component-{{ $this->mod->id }}"
        :commentable="$this->mod"
    />
</div>
