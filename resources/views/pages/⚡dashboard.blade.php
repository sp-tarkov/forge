<?php

declare(strict_types=1);

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::base')] class extends Component {};
?>

<x-slot:title>
    {{ __('Your Dashboard - The Forge') }}
</x-slot>

<x-slot:description>
    {{ __('The dashboard for your account on the Forge.') }}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
        {{ __('Dashboard') }}
    </h2>
</x-slot>

<div>
    <livewire:timezone-warning />

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        @session('status')
            <flux:callout
                icon="check-circle"
                color="green"
                class="mb-6"
            >
                <flux:callout.text>{{ $value }}</flux:callout.text>
            </flux:callout>
        @endsession

        <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
            <x-welcome />
        </div>
    </div>
</div>
