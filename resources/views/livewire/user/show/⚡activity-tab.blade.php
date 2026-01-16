<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

new #[Lazy] class extends Component {
    /**
     * The user ID whose activity is being shown.
     */
    public int $userId;

    /**
     * Mount the component.
     */
    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Get the user.
     */
    #[Computed]
    public function user(): User
    {
        return User::query()->findOrFail($this->userId);
    }
};
?>

@placeholder
    <div
        id="activity"
        class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 text-gray-800 dark:text-gray-200 drop-shadow-2xl"
    >
        <flux:skeleton.group class="space-y-4">
            {{-- Activity items --}}
            @for ($i = 0; $i < 5; $i++)
                <div class="flex items-start gap-3">
                    <flux:skeleton class="h-8 w-8 rounded-full shrink-0" />
                    <div class="flex-1 space-y-2">
                        <flux:skeleton class="h-4 w-3/4 rounded" />
                        <flux:skeleton class="h-3 w-24 rounded" />
                    </div>
                </div>
            @endfor
        </flux:skeleton.group>
    </div>
@endplaceholder

<div
    id="activity"
    class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 text-gray-800 dark:text-gray-200 drop-shadow-2xl"
>
    <livewire:user-activity :user="$this->user" />
</div>
