<footer class="bg-gray-900" aria-labelledby="footer-heading">
    <h2 id="footer-heading" class="sr-only">Footer</h2>
    <div class="mx-auto max-w-7xl px-6 pb-8 pt-14 sm:pt-20 lg:px-8 lg:pt-29">
        <div class="xl:grid xl:grid-cols-3 xl:gap-8">
            <div>
                <p class="text-lg italic font-extrabold leading-6 text-white">The Forge</p>
            </div>
            <div class="mt-16 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8 xl:col-span-2 xl:mt-0">
                <div class="sm:order-first">
                    <h3 class="text-sm font-semibold leading-6 text-white">Single Player Tarkov</h3>
                    <ul role="list" class="mt-6 space-y-4">
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">About</a></li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Articles</a></li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Documentation</a></li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Mods</a></li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Support</a></li>
                    </ul>
                </div>
                <div class="sm:order-last">
                    <h3 class="text-sm font-semibold leading-6 text-white">Escape from Tarkov</h3>
                    <ul role="list" class="mt-6 space-y-4">
                        <li>
                            <a href="https://www.escapefromtarkov.com/preorder-page" class="text-sm leading-6 text-gray-300 hover:text-white">Purchase</a>
                        </li>
                        <li>
                            <a href="https://escapefromtarkov.fandom.com/wiki/Escape_from_Tarkov_Wiki" class="text-sm leading-6 text-gray-300 hover:text-white">Wiki</a>
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold leading-6 text-white">Legal</h3>
                    <ul role="list" class="mt-6 space-y-4">
                        <li>
                            <a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Community Guidelines</a>
                        </li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Terms of Service</a>
                        </li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Privacy Policy</a></li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">Disclaimer</a></li>
                        <li><a href="#" class="text-sm leading-6 text-gray-300 hover:text-white">DMCA</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="mt-8 border-t border-white/10 pt-8 md:flex md:items-center md:justify-between">
            <p class="mt-8 text-xs leading-5 text-gray-400 md:order-1 md:mt-0">&copy; {{ date('Y') }} {{ config('app.name', 'The Forge') }}. All rights reserved.</p>
        </div>
    </div>
</footer>
