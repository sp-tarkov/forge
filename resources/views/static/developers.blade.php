<x-layouts::base>
    <x-slot name="title">
        {{ __('The Forge API') }}
    </x-slot>
    <x-slot name="description">
        {{ __('The Forge API is open, read-only, and free to use. Build mod managers, update checkers, dependency resolvers, and more on top of the Single Player Tarkov mod catalogue.') }}
    </x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 w-full">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <flux:icon
                    name="code-bracket"
                    class="size-8 text-gray-800 dark:text-gray-100"
                />
                <h2
                    class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-100 leading-tight whitespace-nowrap">
                    {{ __('The Forge API') }}
                </h2>
                <flux:badge
                    icon="lock-open"
                    color="green"
                    class="backdrop-blur-sm"
                >
                    Open
                </flux:badge>
            </div>
            <a
                href="{{ url('/docs/index.html') }}"
                target="_blank"
                class="group relative inline-flex shrink-0 items-center justify-center rounded-lg bg-gradient-to-r from-cyan-700 to-cyan-600 px-3 py-2.5 sm:px-6 sm:py-3 text-base font-semibold text-white shadow-xl border border-cyan-600 hover:from-cyan-600 hover:to-cyan-500 hover:border-cyan-500 hover:shadow-2xl hover:shadow-cyan-500/25 active:scale-95 transition-all duration-300 backdrop-blur-sm transform hover:-translate-y-0.5"
            >
                <div class="relative flex items-center">
                    <flux:icon
                        name="book-open"
                        class="mr-0 sm:mr-2 h-5 w-5 transform group-hover:scale-110 transition-transform duration-200"
                    />
                    <span class="hidden sm:inline">API Reference</span>
                </div>
            </a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div
            class="bg-white dark:bg-gray-900 overflow-hidden shadow-xl dark:shadow-gray-900 rounded-none sm:rounded-lg">
            <div class="px-4 py-8 sm:px-6 lg:px-8">

                {{-- Hero Section --}}
                <div
                    class="relative overflow-hidden bg-gradient-to-br from-gray-100 via-white to-gray-50 dark:from-gray-900 dark:via-black dark:to-gray-950 ring-1 ring-gray-200 dark:ring-white/10 rounded-2xl shadow-2xl mb-12">
                    {{-- Decorative accent glows --}}
                    <div
                        class="pointer-events-none absolute -top-24 left-1/4 size-80 -translate-x-1/2 rounded-full bg-cyan-400/25 blur-3xl dark:bg-cyan-500/25"
                    ></div>
                    <div
                        class="pointer-events-none absolute -bottom-24 right-1/4 size-80 translate-x-1/2 rounded-full bg-blue-400/20 blur-3xl dark:bg-indigo-500/25"
                    ></div>
                    {{-- Dot grid texture --}}
                    <div
                        class="absolute inset-0 opacity-20 dark:opacity-25"
                        style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,0.08) 1px, transparent 0); background-size: 4px 4px;"
                    ></div>
                    <div
                        class="absolute inset-0 opacity-0 dark:opacity-25"
                        style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.05) 1px, transparent 0); background-size: 4px 4px;"
                    ></div>

                    <div class="relative px-6 py-8 sm:px-8 sm:py-10">
                        <div class="mx-auto max-w-4xl text-center">
                            <div class="mb-4 flex items-center justify-center gap-3">
                                <span
                                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-cyan-500/10 ring-1 ring-cyan-500/20">
                                    <flux:icon
                                        name="code-bracket"
                                        class="size-6 text-cyan-600 dark:text-cyan-400"
                                    />
                                </span>
                                <h1
                                    class="text-3xl font-bold tracking-tight text-gray-800 dark:text-gray-100 sm:text-4xl">
                                    The Forge API
                                </h1>
                            </div>
                            <p class="text-lg text-gray-600 dark:text-gray-300 mb-6 max-w-3xl mx-auto">
                                Build tools for the Single Player Tarkov community on top of the same data that powers
                                Forge. Every endpoint is publicly accessible and requires no authentication or API key.
                            </p>

                            {{-- At-a-glance pills --}}
                            <div
                                class="flex flex-wrap justify-center gap-3 text-sm font-medium text-gray-600 dark:text-gray-300 mb-6">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-white/10 px-3 py-1.5">
                                    <flux:icon.lock-open class="size-4 text-green-500" /> No authentication
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-white/10 px-3 py-1.5">
                                    <flux:icon.eye class="size-4 text-cyan-500" /> Read-only
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-white/10 px-3 py-1.5">
                                    <flux:icon.code-bracket class="size-4 text-blue-500" /> JSON
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-white/10 px-3 py-1.5">
                                    <flux:icon.bolt class="size-4 text-amber-500" /> ~300 req/min
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-white/10 px-3 py-1.5">
                                    <flux:icon.document-text class="size-4 text-purple-500" /> OpenAPI + Postman
                                </span>
                            </div>

                            {{-- Base URL --}}
                            <div class="mx-auto max-w-2xl text-left">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                                    Base URL
                                </p>
                                <div
                                    class="flex items-center gap-2 rounded-xl bg-gray-900 dark:bg-black ring-1 ring-white/10 px-4 py-3 font-mono text-sm text-cyan-300 overflow-x-auto">
                                    <flux:icon.server class="size-4 shrink-0 text-gray-500" />
                                    <span>{{ url('/api/v0') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Quick Start --}}
                <div class="mb-12">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:icon
                            name="rocket-launch"
                            class="size-6 text-cyan-600 dark:text-cyan-400"
                        />
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Your first request</h2>
                    </div>
                    <p class="text-gray-600 dark:text-gray-300 mb-6 max-w-3xl">
                        No setup, no key, no sign-up. Paste this into a terminal and you are talking to the API in
                        seconds. The full reference shows the same examples in JavaScript, PHP, and Python.
                    </p>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:items-stretch">
                        {{-- Request window --}}
                        <div
                            class="static-content flex h-full flex-col overflow-hidden rounded-2xl shadow-lg ring-1 ring-black/10 dark:ring-white/10">
                            <div class="flex items-center gap-2 border-b border-white/10 bg-gray-800 px-4 py-2.5">
                                <span
                                    class="flex gap-1.5"
                                    aria-hidden="true"
                                >
                                    <span class="size-3 rounded-full bg-red-400/90"></span>
                                    <span class="size-3 rounded-full bg-yellow-400/90"></span>
                                    <span class="size-3 rounded-full bg-green-400/90"></span>
                                </span>
                                <span class="ml-2 inline-flex items-center gap-1.5 text-xs font-medium text-gray-400">
                                    <flux:icon.command-line class="size-3.5" />
                                    Request
                                </span>
                            </div>
                            <pre class="m-0 flex-1 overflow-x-auto bg-[#0d1117] text-sm leading-relaxed text-gray-100"><code class="language-bash">curl "{{ url('/api/v0') }}/mods?per_page=1"</code></pre>
                        </div>
                        {{-- Response window --}}
                        <div
                            class="static-content flex h-full flex-col overflow-hidden rounded-2xl shadow-lg ring-1 ring-black/10 dark:ring-white/10">
                            <div class="flex items-center gap-2 border-b border-white/10 bg-gray-800 px-4 py-2.5">
                                <span
                                    class="flex gap-1.5"
                                    aria-hidden="true"
                                >
                                    <span class="size-3 rounded-full bg-red-400/90"></span>
                                    <span class="size-3 rounded-full bg-yellow-400/90"></span>
                                    <span class="size-3 rounded-full bg-green-400/90"></span>
                                </span>
                                <span class="ml-2 inline-flex items-center gap-1.5 text-xs font-medium text-gray-400">
                                    <flux:icon.code-bracket class="size-3.5" />
                                    Response
                                </span>
                                <span
                                    class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-green-500/15 px-2 py-0.5 text-[11px] font-semibold text-green-400 ring-1 ring-green-500/30">
                                    <span class="size-1.5 rounded-full bg-green-400"></span>
                                    200 OK
                                </span>
                            </div>
                            <pre class="m-0 flex-1 overflow-x-auto bg-[#0d1117] text-sm leading-relaxed text-gray-100"><code class="language-json">{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Raid Time Adjuster",
      "slug": "raid-time-adjuster",
      "downloads": 55212644,
      "fika_compatibility": true
    }
  ],
  "links": { "next": null, "prev": null },
  "meta": { "current_page": 1, "per_page": 1, "total": 842 }
}</code></pre>
                        </div>
                    </div>
                </div>

                {{-- What You Can Build --}}
                <div class="mb-12">
                    <div class="flex items-center gap-2 mb-6">
                        <flux:icon
                            name="wrench-screwdriver"
                            class="size-6 text-cyan-600 dark:text-cyan-400"
                        />
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">What you can build</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            class="rounded-2xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950/60 ring-1 ring-gray-200/80 dark:ring-white/10 p-6 shadow-sm transition duration-300 hover:shadow-md hover:ring-cyan-300/60 dark:hover:ring-cyan-500/30">
                            <flux:icon
                                name="squares-2x2"
                                class="size-10 rounded-xl bg-cyan-500/10 p-2.5 text-cyan-600 dark:text-cyan-400 mb-4 ring-1 ring-cyan-500/20"
                            />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Mod managers &amp; browsers</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                List, search, and filter the full catalogue by category, SPT version, or Fika support,
                                with pagination built in.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950/60 ring-1 ring-gray-200/80 dark:ring-white/10 p-6 shadow-sm transition duration-300 hover:shadow-md hover:ring-cyan-300/60 dark:hover:ring-cyan-500/30">
                            <flux:icon
                                name="arrow-path"
                                class="size-10 rounded-xl bg-cyan-500/10 p-2.5 text-cyan-600 dark:text-cyan-400 mb-4 ring-1 ring-cyan-500/20"
                            />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Update checkers</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Compare a user's installed mods against the latest published versions and tell them
                                exactly what is out of date.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950/60 ring-1 ring-gray-200/80 dark:ring-white/10 p-6 shadow-sm transition duration-300 hover:shadow-md hover:ring-cyan-300/60 dark:hover:ring-cyan-500/30">
                            <flux:icon
                                name="puzzle-piece"
                                class="size-10 rounded-xl bg-cyan-500/10 p-2.5 text-cyan-600 dark:text-cyan-400 mb-4 ring-1 ring-cyan-500/20"
                            />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Dependency resolvers</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Resolve a mod or addon's full dependency tree before install so nothing is left missing.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950/60 ring-1 ring-gray-200/80 dark:ring-white/10 p-6 shadow-sm transition duration-300 hover:shadow-md hover:ring-cyan-300/60 dark:hover:ring-cyan-500/30">
                            <flux:icon
                                name="check-badge"
                                class="size-10 rounded-xl bg-cyan-500/10 p-2.5 text-cyan-600 dark:text-cyan-400 mb-4 ring-1 ring-cyan-500/20"
                            />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Compatibility tooling</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Filter mods by an SPT SemVer constraint or Fika compatibility to surface only what works
                                with a given build.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950/60 ring-1 ring-gray-200/80 dark:ring-white/10 p-6 shadow-sm transition duration-300 hover:shadow-md hover:ring-cyan-300/60 dark:hover:ring-cyan-500/30">
                            <flux:icon
                                name="chat-bubble-left-right"
                                class="size-10 rounded-xl bg-cyan-500/10 p-2.5 text-cyan-600 dark:text-cyan-400 mb-4 ring-1 ring-cyan-500/20"
                            />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Bots &amp; dashboards</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Pull download counts, featured flags, and categories into Discord bots, stat sites, or
                                community dashboards.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950/60 ring-1 ring-gray-200/80 dark:ring-white/10 p-6 shadow-sm transition duration-300 hover:shadow-md hover:ring-cyan-300/60 dark:hover:ring-cyan-500/30">
                            <flux:icon
                                name="sparkles"
                                class="size-10 rounded-xl bg-cyan-500/10 p-2.5 text-cyan-600 dark:text-cyan-400 mb-4 ring-1 ring-cyan-500/20"
                            />
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Whatever you dream up</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                The data is open. If you build something neat with it, come share it with us on Discord.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Resource Map --}}
                <div class="mb-12">
                    <div class="flex items-center gap-2 mb-6">
                        <flux:icon
                            name="map"
                            class="size-6 text-cyan-600 dark:text-cyan-400"
                        />
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Resource map</h2>
                    </div>
                    <div
                        class="overflow-hidden rounded-2xl bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-950/60 ring-1 ring-gray-200/80 dark:ring-white/10 shadow-sm divide-y divide-gray-200/70 dark:divide-white/10">
                        {{-- Mods --}}
                        <div>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-gray-100/70 dark:bg-white/5 px-5 py-3">
                                <flux:icon.cube
                                    class="size-7 rounded-lg bg-cyan-500/10 p-1.5 text-cyan-600 dark:text-cyan-400 ring-1 ring-cyan-500/20" />
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Mods</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">List and inspect mods, versions,
                                    dependencies, and updates.</span>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-white/5">
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code class="font-mono text-sm text-gray-700 dark:text-gray-200">/mods</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">List
                                        mods</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code class="font-mono text-sm text-gray-700 dark:text-gray-200">/mod/{id}</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Mod
                                        details</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code
                                        class="font-mono text-sm text-gray-700 dark:text-gray-200">/mod/{id}/versions</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Version
                                        history</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code
                                        class="font-mono text-sm text-gray-700 dark:text-gray-200">/mods/dependencies</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Resolve
                                        dependencies</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code class="font-mono text-sm text-gray-700 dark:text-gray-200">/mods/updates</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Check for
                                        updates</span>
                                </div>
                            </div>
                        </div>
                        {{-- Addons --}}
                        <div>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-gray-100/70 dark:bg-white/5 px-5 py-3">
                                <flux:icon.puzzle-piece
                                    class="size-7 rounded-lg bg-cyan-500/10 p-1.5 text-cyan-600 dark:text-cyan-400 ring-1 ring-cyan-500/20" />
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Addons</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">The same shape as mods, for addon
                                    packages.</span>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-white/5">
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code class="font-mono text-sm text-gray-700 dark:text-gray-200">/addons</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">List
                                        addons</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code class="font-mono text-sm text-gray-700 dark:text-gray-200">/addon/{id}</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Addon
                                        details</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code
                                        class="font-mono text-sm text-gray-700 dark:text-gray-200">/addon/{id}/versions</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Version
                                        history</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code
                                        class="font-mono text-sm text-gray-700 dark:text-gray-200">/addons/dependencies</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Resolve
                                        dependencies</span>
                                </div>
                            </div>
                        </div>
                        {{-- Categories & SPT versions --}}
                        <div>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-gray-100/70 dark:bg-white/5 px-5 py-3">
                                <flux:icon.tag
                                    class="size-7 rounded-lg bg-cyan-500/10 p-1.5 text-cyan-600 dark:text-cyan-400 ring-1 ring-cyan-500/20" />
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Categories &amp; SPT versions
                                </h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Reference data for filters and
                                    compatibility.</span>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-white/5">
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code
                                        class="font-mono text-sm text-gray-700 dark:text-gray-200">/mod-categories</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">List
                                        categories</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code
                                        class="font-mono text-sm text-gray-700 dark:text-gray-200">/mod-categories/{identifier}</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Category
                                        details</span>
                                </div>
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code class="font-mono text-sm text-gray-700 dark:text-gray-200">/spt/versions</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">SPT
                                        versions</span>
                                </div>
                            </div>
                        </div>
                        {{-- Health --}}
                        <div>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 bg-gray-100/70 dark:bg-white/5 px-5 py-3">
                                <flux:icon.signal
                                    class="size-7 rounded-lg bg-cyan-500/10 p-1.5 text-cyan-600 dark:text-cyan-400 ring-1 ring-cyan-500/20" />
                                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Health</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">Uptime and connectivity
                                    checks.</span>
                            </div>
                            <div class="divide-y divide-gray-100 dark:divide-white/5">
                                <div class="flex items-center gap-3 px-5 py-2.5">
                                    <span
                                        class="inline-flex w-12 shrink-0 justify-center rounded bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">GET</span>
                                    <code class="font-mono text-sm text-gray-700 dark:text-gray-200">/ping</code>
                                    <span class="ml-auto truncate pl-3 text-xs text-gray-500 dark:text-gray-400">Health
                                        check</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Stability & Fair Use --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:items-stretch mb-12">
                    {{-- Stability --}}
                    <div class="relative h-full overflow-hidden rounded-2xl">
                        <div
                            class="absolute -top-8 -right-8 text-yellow-600/15 dark:text-yellow-400/10 select-none pointer-events-none z-0">
                            <flux:icon
                                name="beaker"
                                class="size-48"
                            />
                        </div>
                        <div
                            class="relative z-10 h-full bg-yellow-50 dark:bg-yellow-950/50 ring-1 ring-yellow-200 dark:ring-yellow-800/60 rounded-2xl p-6">
                            <h3 class="font-semibold text-lg text-yellow-900 dark:text-yellow-100 mb-4">A note on
                                stability</h3>
                            <p class="text-yellow-800 dark:text-yellow-200 text-sm leading-6">
                                The API is versioned and currently sits at <code
                                    class="bg-yellow-100 dark:bg-yellow-900 px-1 py-0.5 rounded text-xs">v0</code>. It is
                                in active development, so fields and behaviour may change as it matures. Pin to the
                                versioned base path, and watch our Discord for announcements before you ship anything
                                you intend to support long term.
                            </p>
                        </div>
                    </div>

                    {{-- Fair Use --}}
                    <div class="relative h-full overflow-hidden rounded-2xl">
                        <div
                            class="absolute -top-8 -right-8 text-blue-600/15 dark:text-blue-400/10 select-none pointer-events-none z-0">
                            <flux:icon
                                name="hand-raised"
                                class="size-48"
                            />
                        </div>
                        <div
                            class="relative z-10 h-full bg-blue-50 dark:bg-blue-950/50 ring-1 ring-blue-200 dark:ring-blue-800/60 rounded-2xl p-6">
                            <h3 class="font-semibold text-lg text-blue-900 dark:text-blue-100 mb-4">Be a good citizen</h3>
                            <ul class="space-y-3 text-sm">
                                <li class="flex items-start">
                                    <flux:icon
                                        name="check-circle"
                                        class="size-4 mt-0.5 mr-3 text-blue-600 flex-shrink-0"
                                    />
                                    <span class="text-blue-800 dark:text-blue-200">Cache responses and avoid polling
                                        harder than you need to.</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon
                                        name="check-circle"
                                        class="size-4 mt-0.5 mr-3 text-blue-600 flex-shrink-0"
                                    />
                                    <span class="text-blue-800 dark:text-blue-200">Send a descriptive <code
                                            class="bg-blue-100 dark:bg-blue-900 px-1 py-0.5 rounded text-xs">User-Agent</code>
                                        so we can reach you if needed.</span>
                                </li>
                                <li class="flex items-start">
                                    <flux:icon
                                        name="check-circle"
                                        class="size-4 mt-0.5 mr-3 text-blue-600 flex-shrink-0"
                                    />
                                    <span class="text-blue-800 dark:text-blue-200">Respect the rate limit and our <a
                                            href="{{ route('static.terms') }}"
                                            wire:navigate
                                            class="underline hover:text-blue-600 dark:hover:text-blue-300"
                                        >Terms of Service</a>.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Ready to build --}}
                <div
                    class="rounded-2xl ring-1 ring-gray-200 dark:ring-white/10 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-900 dark:to-gray-950 p-8 text-center">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-2">Ready to build?</h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-6 max-w-2xl mx-auto">
                        Dive into the full reference, grab the spec or Postman collection, or come ask questions and show
                        off what you are building.
                    </p>
                    <div class="flex flex-wrap justify-center gap-4">
                        <a
                            href="{{ url('/docs/index.html') }}"
                            target="_blank"
                            class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-cyan-700 to-cyan-600 px-6 py-3 text-base font-semibold text-white shadow-lg border border-cyan-600 hover:from-cyan-600 hover:to-cyan-500 transition-all duration-300"
                        >
                            <flux:icon.book-open class="size-5" />
                            Read the full reference
                        </a>
                        <a
                            href="{{ url('/docs/openapi.yaml') }}"
                            target="_blank"
                            class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-white dark:bg-gray-800 px-6 py-3 text-base font-semibold text-gray-800 dark:text-gray-100 shadow-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300"
                        >
                            <flux:icon.document-text class="size-5" />
                            OpenAPI spec
                        </a>
                        <a
                            href="{{ url('/docs/collection.json') }}"
                            target="_blank"
                            class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-white dark:bg-gray-800 px-6 py-3 text-base font-semibold text-gray-800 dark:text-gray-100 shadow-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300"
                        >
                            <flux:icon.rectangle-stack class="size-5" />
                            Postman collection
                        </a>
                        <a
                            href="https://discord.com/invite/Xn9msqQZan"
                            target="_blank"
                            class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-white dark:bg-gray-800 px-6 py-3 text-base font-semibold text-gray-800 dark:text-gray-100 shadow-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300"
                        >
                            <flux:icon.chat-bubble-left-right class="size-5" />
                            Ask on Discord
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::base>
