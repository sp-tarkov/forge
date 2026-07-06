<div>
    <div class="border-b border-gray-800 p-6 lg:p-8">
        <x-application-logo class="block h-12 w-auto" />
        <h1 class="mt-4 text-2xl font-medium text-white">{{ __('Welcome') }}
            {{ auth()->user()->name }}!</h1>
        <p class="mt-2 leading-relaxed text-gray-400">
            {{ __("Here's what's happening with your account.") }}</p>
    </div>

    {{-- Notifications Section --}}
    @livewire('notification-center')
</div>
