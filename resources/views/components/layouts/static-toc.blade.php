@props(['title' => 'Table of Contents'])

<x-layouts.base>
    <x-slot name="title">
        {{ $pageTitle ?? 'Static Content' }}
    </x-slot>
    <x-slot name="description">
        {{ $pageDescription ?? 'Static content page' }}
    </x-slot>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
            {{ $pageTitle ?? 'Static Content' }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="lg:flex lg:gap-8">
            <!-- Table of Contents Sidebar -->
            <x-table-of-contents :title="$title">
                {{ $tableOfContents }}
            </x-table-of-contents>

            <!-- Main Content -->
            <div class="flex-1 bg-white dark:bg-gray-900 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="px-4 py-8 sm:px-6 lg:px-8 prose dark:prose-invert max-w-none static-content">
                    <!-- Page Title in Content -->
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-6">{{ $pageTitle ?? 'Static Content' }}</h1>
                    
                    <!-- Main Content Slot -->
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for smooth scrolling and active state -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling behavior
            document.documentElement.style.scrollBehavior = 'smooth';

            // Update active nav link based on scroll position
            const navLinks = document.querySelectorAll('.nav-link');
            const sections = document.querySelectorAll('h2[id], h3[id], h4[id]');

            function updateActiveLink() {
                let current = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop - 100;
                    if (window.scrollY >= sectionTop) {
                        current = section.getAttribute('id');
                    }
                });

                navLinks.forEach(link => {
                    link.classList.remove('text-cyan-600', 'dark:text-cyan-400', 'bg-cyan-50', 'dark:bg-cyan-900/50');
                    link.classList.add('text-gray-600', 'dark:text-gray-400');

                    if (link.getAttribute('href') === '#' + current) {
                        link.classList.remove('text-gray-600', 'dark:text-gray-400');
                        link.classList.add('text-cyan-600', 'dark:text-cyan-400', 'bg-cyan-50', 'dark:bg-cyan-900/50');
                    }
                });
            }

            window.addEventListener('scroll', updateActiveLink);
            updateActiveLink(); // Initial call
        });
    </script>
</x-layouts.base>