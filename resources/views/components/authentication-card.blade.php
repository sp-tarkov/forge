<div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900 overflow-hidden">
    {{-- Video Background --}}
    <video autoplay muted loop playsinline class="absolute inset-0 h-full w-full object-cover">
        <source src="{{ Vite::asset('resources/video/welcome.mp4') }}" type="video/mp4">
    </video>
    
    {{-- Dimming Overlay --}}
    <div class="absolute inset-0 bg-black/60 dark:bg-black/70"></div>
    
    {{-- Content Container --}}
    <div class="relative z-10">
        {{ $logo }}
    </div>

    <div class="relative z-10 w-full sm:max-w-md mt-6 px-6 py-4 bg-gray-800/95 dark:bg-gray-900/95 backdrop-blur-sm shadow-xl overflow-hidden sm:rounded-lg">
        {{ $slot }}
    </div>
</div>
