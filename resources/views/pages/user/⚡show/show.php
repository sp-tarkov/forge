<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mchev\Banhammer\Models\Ban;

new #[Layout('layouts::base')] class extends Component
{
    /**
     * The user being viewed.
     */
    public User $user;

    /**
     * Mount the component.
     */
    public function mount(int $userId, string $slug): void
    {
        $this->user = $this->getUser($userId);

        $this->enforceCanonicalSlug($this->user, $slug);

        Gate::authorize('view', $this->user);

        $this->handleBannedUser();
    }

    /**
     * Mount the component.
     */
    public function getUser(int $userId): User
    {
        return User::query()->findOrFail($userId);
    }

    /**
     * Get the total mod count visible to the current user.
     */
    public function getModCount(): int
    {
        return $this->user->visibleModsFor(auth()->user())->count('mods.id');
    }

    /**
     * Get the total addon count visible to the current user.
     */
    public function getAddonCount(): int
    {
        return $this->user->visibleAddonsFor(auth()->user())->count('addons.id');
    }

    /**
     * Get the active ban for the user.
     */
    public function getActiveBan(): ?Ban
    {
        if ($this->user->isNotBanned()) {
            return null;
        }

        /** @var Ban|null */
        return $this->user
            ->bans()
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->first();
    }

    /**
     * Get view data.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'user' => $this->user,
            'openGraphImage' => $this->user->profile_photo_path,
            'modCount' => $this->getModCount(),
            'addonCount' => $this->getAddonCount(),
            'activeBan' => $this->getActiveBan(),
        ];
    }

    /**
     * Handle displaying profile for banned users.
     */
    protected function handleBannedUser(): void
    {
        // Load bans relationship to check ban status
        $this->user->loadMissing('bans');

        // If user is not banned, continue normally
        if ($this->user->isNotBanned()) {
            return;
        }

        // Get the current viewer
        $viewer = Auth::user();

        // If viewer is admin or moderator, they can see the profile with ban info
        if ($viewer && $viewer->isModOrAdmin()) {
            return;
        }

        // For guests and normal users, redirect to banned user page
        /** @var Ban|null $activeBan */
        $activeBan = $this->user
            ->bans()
            ->where(function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            })
            ->first();

        // Flash ban expiry date to session if available
        if ($activeBan && $activeBan->getAttribute('expired_at')) {
            session()->flash('ban_expires_at', $activeBan->getAttribute('expired_at'));
        }

        $this->redirectRoute('user.banned', navigate: true);
    }

    /**
     * Redirect to the canonical slug route if the given slug is incorrect.
     */
    protected function enforceCanonicalSlug(User $user, string $slug): void
    {
        if ($user->slug !== $slug) {
            $this->redirectRoute('user.show', [
                'userId' => $user->id,
                'slug' => $user->slug,
            ]);
        }
    }
};
