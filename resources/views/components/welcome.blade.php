<div>
    <div class="p-6 lg:p-8 border-b border-gray-200 dark:border-gray-800">
        <x-application-logo class="block h-12 w-auto" />
        <h1 class="mt-4 text-2xl font-medium text-gray-900 dark:text-white">{{ __('Welcome') }}
            {{ auth()->user()->name }}!</h1>
        <p class="mt-2 text-gray-500 dark:text-gray-400 leading-relaxed">
            {{ __("Here's what's happening with your account.") }}</p>
    </div>

    {{-- Notifications Section --}}
    @livewire('notification-center')
</div>
