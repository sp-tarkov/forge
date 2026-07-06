<div class="relative flex min-h-screen flex-col items-center overflow-hidden bg-gray-900 pt-6 sm:justify-center sm:pt-0">
    {{-- Video Background --}}
    <video
        autoplay
        muted
        loop
        playsinline
        class="absolute inset-0 h-full w-full object-cover"
    >
        <source
            src="{{ Vite::asset('resources/video/welcome.mp4') }}"
            type="video/mp4"
        >
    </video>

    {{-- Dimming Overlay --}}
    <div class="absolute inset-0 bg-black/70"></div>

    {{-- Content Container --}}
    <div class="relative z-10">
        {{ $logo }}
    </div>

    <div
        class="relative z-10 mt-6 w-full overflow-hidden bg-gray-900/95 p-6 shadow-xl backdrop-blur-sm sm:max-w-md sm:rounded-lg">
        {{ $slot }}
    </div>
</div>
