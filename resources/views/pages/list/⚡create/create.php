<?php

declare(strict_types=1);

use App\Enums\ListVisibility;
use App\Livewire\Forms\ModListForm;
use App\Models\ModList;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component
{
    public ModListForm $form;

    public function mount(): void
    {
        Gate::authorize('create', ModList::class);

        $this->form->visibility = ListVisibility::Private->value;
    }

    public function save(): void
    {
        Gate::authorize('create', ModList::class);

        if (! $this->withinCreationRateLimit()) {
            return;
        }

        $list = $this->form->save();

        $this->redirectRoute('list.show', [
            'listId' => $list->id,
            'slug' => $list->slug,
        ]);
    }

    /**
     * Guard list creation against rapid-fire abuse. Staff are exempt.
     */
    private function withinCreationRateLimit(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User || $user->isModOrAdmin()) {
            return true;
        }

        $key = 'mod-list-creation:'.$user->id;
        $max = config()->integer('mod-lists.rate_limiting.create_max_attempts', 15);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->addError('form.title', __('You are creating lists too quickly. Please wait :seconds seconds and try again.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return false;
        }

        RateLimiter::hit($key, config()->integer('mod-lists.rate_limiting.create_duration_seconds', 60));

        return true;
    }
};
