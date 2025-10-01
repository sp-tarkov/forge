<footer class="bg-gray-900" aria-labelledby="footer-heading">
    <h2 id="footer-heading" class="sr-only">Footer</h2>
    <div class="mx-auto max-w-7xl px-6 pb-8 pt-14 sm:pt-20 lg:px-8 lg:pt-29">
        <div class="xl:grid xl:grid-cols-3 xl:gap-8">
            <div>
                <x-application-mark />
                <p class="mt-6 flex space-x-4">
                    <a href="https://github.com/sp-tarkov/forge" target="_blank" title="{{ __('View on GitHub') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 text-white hover:text-gray-300">
                            <path d="M12 0C5.374 0 0 5.373 0 12 0 17.302 3.438 21.8 8.207 23.387c.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </a>
                    <a href="https://discord.com/invite/Xn9msqQZan" title="{{ __('Join Our Discord!') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 127.14 96.36" class="w-6 h-6 text-white hover:text-gray-300">
                            <path fill="currentColor" d="M107.7,8.07A105.15,105.15,0,0,0,81.47,0a72.06,72.06,0,0,0-3.36,6.83A97.68,97.68,0,0,0,49,6.83,72.37,72.37,0,0,0,45.64,0,105.89,105.89,0,0,0,19.39,8.09C2.79,32.65-1.71,56.6.54,80.21h0A105.73,105.73,0,0,0,32.71,96.36,77.7,77.7,0,0,0,39.6,85.25a68.42,68.42,0,0,1-10.85-5.18c.91-.66,1.8-1.34,2.66-2a75.57,75.57,0,0,0,64.32,0c.87.71,1.76,1.39,2.66,2a68.68,68.68,0,0,1-10.87,5.19,77,77,0,0,0,6.89,11.1A105.25,105.25,0,0,0,126.6,80.22h0C129.24,52.84,122.09,29.11,107.7,8.07ZM42.45,65.69C36.18,65.69,31,60,31,53s5-12.74,11.43-12.74S54,46,53.89,53,48.84,65.69,42.45,65.69Zm42.24,0C78.41,65.69,73.25,60,73.25,53s5-12.74,11.44-12.74S96.23,46,96.12,53,91.08,65.69,84.69,65.69Z" />
                        </svg>
                    </a>
                    <x-sf-icon href="https://sanctumfall.com" target="_blank" title="{{ __('Sanctumfall') }}" />
                </p>
                <p class="mt-4 text-sm text-gray-400">
                    This site is open source.<br /><a href="https://github.com/sp-tarkov/forge" target="_blank" class="text-white hover:text-gray-300 underline">Help build it!</a>
                </p>
            </div>
            <div class="mt-16 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 xl:col-span-2 xl:mt-0">
                <div class="sm:order-first">
                    <h3 class="text-sm font-semibold leading-6 text-white">Single Player Tarkov</h3>
                    <ul role="list" class="mt-6 space-y-4">
                        <li><a href="{{ route('static.installer') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">Installer</a></li>
                        <li><a href="{{ route('mods') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">Mods</a></li>
                        <li><a href="https://wiki.sp-tarkov.com" target="_blank" class="text-sm leading-6 text-gray-300 hover:text-white">SPT Wiki</a></li>
                        <li><a href="{{ route('static.contact') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="sm:order-last">
                    <h3 class="text-sm font-semibold leading-6 text-white">Escape from Tarkov</h3>
                    <ul role="list" class="mt-6 space-y-4">
                        <li><a href="https://www.escapefromtarkov.com/preorder-page" class="text-sm leading-6 text-gray-300 hover:text-white">Purchase</a></li>
                        <li><a href="https://escapefromtarkov.fandom.com/wiki/Escape_from_Tarkov_Wiki" class="text-sm leading-6 text-gray-300 hover:text-white">EFT Wiki</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold leading-6 text-white">Legal Stuff</h3>
                    <ul role="list" class="mt-6 space-y-4">
                        <li><a href="{{ route('static.community-standards') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">Community Standards</a></li>
                        <li><a href="{{ route('static.content-guidelines') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">Content Guidelines</a></li>
                        <li><a href="{{ route('static.terms') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">Terms of Service</a></li>
                        <li><a href="{{ route('static.privacy') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">Privacy Policy</a></li>
                        <li><a href="{{ route('static.dmca') }}" wire:navigate class="text-sm leading-6 text-gray-300 hover:text-white">DMCA</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="mt-8 border-t border-white/10 pt-8">
            <div class="flex flex-col space-y-6 sm:flex-row sm:items-center sm:justify-between sm:space-y-0">
                <div class="flex flex-col space-y-2 text-xs text-gray-400">
                    <p>
                        &copy; {{ date('Y') }} {{ config('app.name', 'The Forge') }}. All rights reserved.<br />
                        Escape from Tarkov is a registered trademark of Battlestate Games Limited.<br />
                        The Forge is not affiliated with Battlestate Games Limited in any way.
                    </p>
                </div>
                <div class="flex items-center">
                    <livewire:visitor-tracker />
                </div>
            </div>
        </div>
    </div>
</footer>
