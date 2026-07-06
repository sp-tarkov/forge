<x-slot:title>
    {!! __('Edit Addon Version for :addon - The Forge', ['addon' => $addonVersion->addon->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('Update this version for the :addon addon.', ['addon' => $addonVersion->addon->name]) !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.puzzle-piece class="w-5 h-5" />
        {{ __('Edit Addon Version') }}: {{ $addonVersion->addon->name }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 px-4 sm:px-0">
                <h3 class="text-lg font-medium text-gray-100">Version Information</h3>
                <p class="my-2 text-sm text-gray-400">
                    Update this version for <strong>{{ $addonVersion->addon->name }}</strong>. Specify which mod
                    versions this
                    addon version is compatible with using semver constraints.
                </p>
            </div>

            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-6">
                            @csrf

                            {{-- Version --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Version Number') }}</flux:label>
                                <flux:description>{!! __('The version number for this release. Must follow semantic versioning.') !!}</flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.blur="version"
                                    placeholder="1.0.0"
                                    maxlength="50"
                                />
                                <flux:error name="version" />
                            </flux:field>

                            {{-- Description --}}
                            <flux:field class="col-span-6">
                                <x-markdown-editor
                                    wire-model="description"
                                    name="description"
                                    :label="__('Description')"
                                    :description="__(
                                        'Explain what\'s new or changed in this version. Use markdown for formatting.',
                                    )"
                                    placeholder="This version includes updates to the..."
                                    rows="6"
                                    purify-config="description"
                                />
                            </flux:field>

                            {{-- Download Link --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Download Link') }}</flux:label>
                                <flux:description>
                                    {{ __('Provide a direct download link to the addon file. The addon archive must follow the structure specified in the file submission guidelines or the launcher will not support automatic installs or updates for your addon.') }}
                                </flux:description>
                                <flux:input
                                    type="url"
                                    wire:model.blur="link"
                                    placeholder="https://www.example.com/your-addon-archive.7zip"
                                />
                                <flux:error name="link" />
                            </flux:field>

                            {{-- Mod Version Constraint --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Mod Version Constraint') }}</flux:label>
                                <flux:description>{!! __(
                                    'Specify which mod versions this addon version is compatible with using semantic version constraints. For example, you can use the value ~1.0.0 to match all 1.0 versions. Works just like Composer or NPM. Start typing to see matches below.',
                                ) !!}</flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.live.debounce="modVersionConstraint"
                                    placeholder="~1.0.0"
                                />
                                <flux:error name="modVersionConstraint" />
                                @if (count($matchingModVersions) > 0)
                                    <div class="mt-2 space-y-1">
                                        <p class="text-sm text-gray-400">
                                            {{ __('Current Matching Mod Versions:') }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($matchingModVersions as $version)
                                                <span
                                                    class="inline-flex items-center rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-gray-400"
                                                >
                                                    {{ $version['version'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </flux:field>

                            {{-- Mod Dependencies --}}
                            <flux:field class="col-span-6">
                                <flux:label badge="Optional">{{ __('Mod Dependencies') }}</flux:label>
                                <flux:description>
                                    {{ __('Specify mods that this addon version depends on. Use semantic version constraints to define compatible versions.') }}
                                </flux:description>

                                <div class="space-y-4">
                                    @foreach ($dependencies as $index => $dependency)
                                        <div
                                            wire:key="dependency-{{ $dependency['id'] ?? $index }}"
                                            class="p-4 border border-gray-700 rounded-lg"
                                        >
                                            <div class="flex justify-between items-start mb-3">
                                                <span
                                                    class="text-sm font-medium text-gray-300">{{ __('Dependency #:num', ['num' => $index + 1]) }}</span>
                                                <flux:button
                                                    size="xs"
                                                    variant="outline"
                                                    wire:click="removeDependency({{ $index }})"
                                                    type="button"
                                                >
                                                    {{ __('Remove') }}
                                                </flux:button>
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <flux:field>
                                                    <flux:label>{{ __('Mod') }}</flux:label>
                                                    <livewire:form.mod-autocomplete
                                                        :key="'autocomplete-' . ($dependency['id'] ?? $index)"
                                                        :selected-mod-id="$dependencies[$index]['modId'] ?? ''"
                                                        placeholder="Type to search for a mod..."
                                                        label="Select dependency mod"
                                                        @mod-selected="updateDependencyModId({{ $index }}, $event.detail.modId)"
                                                    />
                                                    <flux:error name="dependencies.{{ $index }}.modId" />
                                                </flux:field>

                                                <flux:field>
                                                    <flux:label>{{ __('Version Constraint') }}</flux:label>
                                                    <flux:input
                                                        type="text"
                                                        wire:model.live.debounce="dependencies.{{ $index }}.constraint"
                                                        placeholder="~1.0.0"
                                                    />
                                                    <flux:error name="dependencies.{{ $index }}.constraint" />
                                                </flux:field>
                                            </div>

                                            @if (isset($matchingDependencyVersions[$index]) && count($matchingDependencyVersions[$index]) > 0)
                                                <div class="mt-3">
                                                    <p class="text-sm text-gray-400 mb-2">
                                                        {{ __('Matching Versions:') }}</p>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($matchingDependencyVersions[$index] as $version)
                                                            <span
                                                                class="inline-flex items-center rounded-md bg-gray-800 px-2 py-1 text-xs font-medium text-gray-400"
                                                            >
                                                                {{ $version['mod_name'] }} v{{ $version['version'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @elseif(!empty($dependencies[$index]['modId']) && !empty($dependencies[$index]['constraint']))
                                                <div class="mt-3">
                                                    <p class="text-sm text-yellow-400">
                                                        {{ __('No matching versions found for this constraint.') }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        wire:click="addDependency"
                                        type="button"
                                    >
                                        {{ __('+ Add Dependency') }}
                                    </flux:button>
                                </div>
                            </flux:field>

                            {{-- VirusTotal Links --}}
                            <flux:field class="col-span-6">
                                <flux:label>{{ __('VirusTotal Links') }}</flux:label>
                                <flux:description>{!! __(
                                    'Provide links to the <a href="https://www.virustotal.com" target="_blank" class="underline text-white hover:text-cyan-200 transition-colors">VirusTotal</a> scan results for your addon files. This helps users verify the safety of your addon. At least one link is required.',
                                ) !!}</flux:description>

                                <div class="space-y-3">
                                    @foreach ($virusTotalLinks as $index => $virusTotalLink)
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <flux:input
                                                    type="url"
                                                    wire:model.blur="virusTotalLinks.{{ $index }}.url"
                                                    placeholder="https://www.virustotal.com/..."
                                                />
                                            </div>
                                            <div class="w-40">
                                                <flux:input
                                                    type="text"
                                                    wire:model.blur="virusTotalLinks.{{ $index }}.label"
                                                    placeholder="Label (optional)"
                                                />
                                            </div>
                                            @if ($index > 0)
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="removeVirusTotalLink({{ $index }})"
                                                    type="button"
                                                    icon="x-mark"
                                                />
                                            @endif
                                        </div>
                                        @error('virusTotalLinks.' . $index . '.url')
                                            <flux:error>{{ $message }}</flux:error>
                                        @enderror
                                    @endforeach

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="addVirusTotalLink"
                                        type="button"
                                        icon="plus"
                                    >
                                        {{ __('Add Link') }}
                                    </flux:button>
                                </div>

                                <flux:error name="virusTotalLinks" />
                            </flux:field>

                            {{-- Published At --}}
                            <flux:field
                                class="col-span-6"
                                x-data="{
                                    now() {
                                        // Format: YYYY-MM-DDTHH:MM
                                        const pad = n => n.toString().padStart(2, '0');
                                        const d = new Date();
                                        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                                    }
                                }"
                            >
                                <flux:label badge="Optional">{{ __('Publish Date') }}</flux:label>
                                <flux:description>
                                    {!! __(
                                        'Select the date and time the addon will be published. If the addon is not published, it will not be discoverable by other users. Leave blank to keep the addon unpublished.',
                                    ) !!}
                                </flux:description>

                                <div class="space-y-3">
                                    @if (auth()->user()->timezone === null)
                                        <flux:callout
                                            icon="exclamation-triangle"
                                            color="orange"
                                            inline="inline"
                                        >
                                            <flux:callout.text>
                                                You have not selected a timezone for your account. You may continue,
                                                but the published date will be interpreted as a UTC date.
                                                Alternatively, you can <a
                                                    href="/user/profile"
                                                    class="underline text-white hover:text-cyan-200 transition-colors"
                                                >edit your profile</a> to set a specific timezone.
                                            </flux:callout.text>
                                        </flux:callout>
                                    @else
                                        <p class="text-sm text-gray-400">
                                            {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()->timezone]) }}
                                        </p>
                                    @endif
                                    <div class="flex gap-2 items-center">
                                        <flux:date-picker
                                            wire:model="publishedAtDate"
                                            clearable
                                        />
                                        <flux:time-picker
                                            wire:model="publishedAtTime"
                                            clearable
                                        />
                                        @if (auth()->user()->timezone !== null)
                                            <flux:button
                                                size="sm"
                                                variant="outline"
                                                @click="$wire.set('publishedAtDate', '{{ now()->timezone(auth()->user()->timezone)->format('Y-m-d') }}'); $wire.set('publishedAtTime', '{{ now()->timezone(auth()->user()->timezone)->format('H:i') }}')"
                                            >Now</flux:button>
                                        @endif
                                    </div>
                                </div>

                                <flux:error name="publishedAtDate" />
                                <flux:error name="publishedAtTime" />
                            </flux:field>

                            {{-- Honeypot --}}
                            <x-honeypot />
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end px-4 py-3 bg-gray-900 border-t-2 border-transparent border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button
                            variant="primary"
                            size="sm"
                            class="my-1.5 text-white hover:bg-cyan-600 bg-cyan-700"
                            type="submit"
                        >{{ __('Update Version') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>