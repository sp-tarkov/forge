<?php

declare(strict_types=1);

use App\Models\ModList;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Livewire\WithPagination;

new #[Lazy] class extends Component
{
    use WithPagination;

    public int $userId;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::query()->findOrFail($this->userId);
    }

    /**
     * @return LengthAwarePaginator<int, ModList>
     */
    #[Computed]
    public function lists(): LengthAwarePaginator
    {
        $viewer = Auth::user();
        $isOwner = $viewer !== null && $viewer->id === $this->userId;

        $query = ModList::query()
            ->where('owner_id', $this->userId)
            ->with(['sptVersion'])
            ->withCount('items');

        if (! $isOwner) {
            $query->public();
        }

        $query->orderByDesc('is_default')
            ->latest('updated_at');

        return $query->paginate(10)->fragment('lists'); // @phpstan-ignore return.type
    }

    #[Computed]
    public function isOwner(): bool
    {
        return Auth::id() === $this->userId;
    }

    #[Computed]
    public function listCount(): int
    {
        return (int) ModList::query()->where('owner_id', $this->userId)->count();
    }
};
