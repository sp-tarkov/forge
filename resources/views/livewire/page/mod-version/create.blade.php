<x-slot:title>
    {!! __('Create a New Version for :mod - The Forge', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:description>
    {!! __('Create a new version for :mod to share with the community.', ['mod' => $mod->name]) !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight flex items-center gap-2">
        <flux:icon.cube-transparent class="w-5 h-5" />
        {{ __('Create Mod Version') }}: {{ $mod->name }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Version Information</h3>
                    <p class="my-2 text-sm/6 text-gray-600 dark:text-gray-400">Add a new version to your mod by filling
                        out this form. It will be unpublished by default.</p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Please ensure you follow the <a
                            href="{{ route('static.community-standards') }}"
                            target="_blank"
                            class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                        >Community Standards</a>
                        and the <a
                            href="{{ route('static.content-guidelines') }}"
                            target="_blank"
                            class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                        >Content Guidelines</a>.
                        Failing to do so will result in your mod being removed from the Forge and possible action being
                        taken against your account.
                    </p>
                </div>
                <div class="px-4 sm:px-0"></div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-6">
                            @csrf

                            @if (empty($modCategoryId))
                                <div class="col-span-6">
                                    <flux:callout
                                        icon="information-circle"
                                        color="cyan"
                                    >
                                        <flux:callout.text>
                                            <div class="space-y-3">
                                                <div>
                                                    <strong>{{ __('Mod Category Required') }}</strong>
                                                    <p class="mt-1 text-sm">
                                                        {{ __('This mod does not have a category set. Please select a category that best describes your mod. This helps users find your mod more easily.') }}
                                                    </p>
                                                </div>
                                                <div class="flex gap-3 items-start">
                                                    <div class="flex-1">
                                                        <flux:select
                                                            wire:model.live="newModCategoryId"
                                                            placeholder="Choose category..."
                                                        >
                                                            @foreach (\App\Models\ModCategory::orderBy('title')->get() as $category)
                                                                <flux:select.option value="{{ $category->id }}">
                                                                    {{ $category->title }}
                                                                </flux:select.option>
                                                            @endforeach
                                                        </flux:select>
                                                    </div>
                                                    <flux:button
                                                        variant="primary"
                                                        size="sm"
                                                        wire:click="saveCategory"
                                                        wire:loading.attr="disabled"
                                                        type="button"
                                                        class="mt-1"
                                                    >
                                                        <span
                                                            wire:loading.remove
                                                            wire:target="saveCategory"
                                                        >{{ __('Save Category') }}</span>
                                                        <span
                                                            wire:loading
                                                            wire:target="saveCategory"
                                                        >{{ __('Saving...') }}</span>
                                                    </flux:button>
                                                </div>
                                                <flux:error name="newModCategoryId" />
                                            </div>
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                            @endif

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

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Download Link') }}</flux:label>
                                <flux:description>
                                    {{ __('Provide a direct download link to the mod file. The mod archive must follow the structure specified in the file submission guidelines or the launcher will not support automatic installs or updates for your mod.') }}
                                </flux:description>
                                <flux:input
                                    type="url"
                                    wire:model.blur="link"
                                    placeholder="https://www.example.com/your-mod-archive.7zip"
                                />
                                <flux:error name="link" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('SPT Version Constraint') }}</flux:label>
                                <flux:description>{!! __(
                                    'Specify which SPT versions this mod version is compatible with using semantic version constraints. For example, you can use the value ~3.11.0 to match all 3.11 versions. Works just like Composer or NPM. Start typing to see matches below.',
                                ) !!}</flux:description>
                                <flux:input
                                    type="text"
                                    wire:model.live.debounce="sptVersionConstraint"
                                    placeholder="~3.11.0"
                                />
                                <flux:error name="sptVersionConstraint" />
                                @if (count($matchingSptVersions) > 0)
                                    <div class="mt-2 space-y-1">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ __('Current Matching SPT Versions:') }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($matchingSptVersions as $version)
                                                <span
                                                    class="badge-version {{ $version['color_class'] }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap"
                                                >
                                                    {{ $version['version'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </flux:field>

                            @if ($this->modGuidRequired && empty($modGuid))
                                <div class="col-span-6">
                                    <flux:callout
                                        icon="information-circle"
                                        color="amber"
                                    >
                                        <flux:callout.text>
                                            <div class="space-y-3">
                                                <div>
                                                    <strong>{{ __('Mod GUID Required') }}</strong>
                                                    <p class="mt-1 text-sm">
                                                        {{ __('This mod version targets SPT 4.0.0 or above, which requires a mod GUID. Enter a unique identifier for your mod in reverse domain notation. This GUID will be saved to the mod and should match the one in your mod files.') }}
                                                        {!! __(
                                                            'Please see the <a href=":url" target="_blank" class="underline hover:no-underline">Content Guidelines</a> for more information.',
                                                            ['url' => route('static.content-guidelines') . '#mod-types-requirements'],
                                                        ) !!}
                                                    </p>
                                                </div>
                                                <div
                                                    class="flex gap-3 items-start"
                                                    x-data="{ count: 0, text: '' }"
                                                >
                                                    <div class="flex-1">
                                                        <flux:input
                                                            type="text"
                                                            wire:model.live="newModGuid"
                                                            maxlength="255"
                                                            x-model="text"
                                                            @input="count = text.length"
                                                            placeholder="com.username.modname"
                                                        />
                                                        <div
                                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                                            x-text="`Max Length: ${count}/255`"
                                                        ></div>
                                                    </div>
                                                    <flux:button
                                                        variant="primary"
                                                        size="sm"
                                                        wire:click="saveGuid"
                                                        wire:loading.attr="disabled"
                                                        type="button"
                                                        class="mt-1"
                                                    >
                                                        <span
                                                            wire:loading.remove
                                                            wire:target="saveGuid"
                                                        >{{ __('Save GUID') }}</span>
                                                        <span
                                                            wire:loading
                                                            wire:target="saveGuid"
                                                        >{{ __('Saving...') }}</span>
                                                    </flux:button>
                                                </div>
                                                <flux:error name="newModGuid" />
                                            </div>
                                        </flux:callout.text>
                                    </flux:callout>
                                </div>
                            @endif

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('VirusTotal Links') }}</flux:label>
                                <flux:description>{!! __(
                                    'Provide links to the <a href="https://www.virustotal.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">VirusTotal</a> scan results for your mod files. This helps users verify the safety of your mod. At least one link is required.',
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

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Fika Compatibility') }}</flux:label>
                                <flux:description>{{ __('Specify whether this mod version is compatible with Fika.') }}
                                </flux:description>
                                <flux:select wire:model.blur="fikaCompatibilityStatus">
                                    <option value="compatible">{{ __('Compatible') }}</option>
                                    <option value="incompatible">{{ __('Incompatible') }}</option>
                                    <option value="unknown">{{ __('Compatibility Unknown') }}</option>
                                </flux:select>
                                <flux:error name="fikaCompatibilityStatus" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Mod Dependencies') }}</flux:label>
                                <flux:description>
                                    {{ __('Specify other mods that this version depends on. Use semantic version constraints to define compatible versions.') }}
                                </flux:description>

                                <div class="space-y-4">
                                    @foreach ($dependencies as $index => $dependency)
                                        <div
                                            wire:key="dependency-{{ $dependency['id'] ?? $index }}"
                                            class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg"
                                        >
                                            <div class="flex justify-between items-start mb-3">
                                                <span
                                                    class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Dependency #:num', ['num' => $index + 1]) }}</span>
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
                                                    <livewire:components.mod-autocomplete
                                                        :key="'autocomplete-' . ($dependency['id'] ?? $index)"
                                                        :exclude-mod-id="$mod->id"
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
                                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                                        {{ __('Matching Versions:') }}</p>
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach ($matchingDependencyVersions[$index] as $version)
                                                            <span
                                                                class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-400"
                                                            >
                                                                {{ $version['mod_name'] }} v{{ $version['version'] }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @elseif(!empty($dependencies[$index]['modId']) && !empty($dependencies[$index]['constraint']))
                                                <div class="mt-3">
                                                    <p class="text-sm text-yellow-600 dark:text-yellow-400">
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

                            <flux:field
                                class="col-span-6"
                                x-data="{
                                    now() {
                                            // Format: YYYY-MM-DDTHH:MM
                                            const pad = n => n.toString().padStart(2, '0');
                                            const d = new Date();
                                            return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                                        },
                                        get pinToSpt() {
                                            return $wire.pinToSptVersions;
                                        },
                                        hasUnpublished: {{ $this->hasUnpublishedSptVersions() ? 'true' : 'false' }}
                                }"
                            >
                                <flux:label badge="Optional">{{ __('Publish Date') }}</flux:label>
                                <flux:description>
                                    @if ($this->hasUnpublishedSptVersions())
                                        {!! __(
                                            'Choose when to publish this mod version. You can either set a specific date or pin it to automatically publish when all of the unpublished SPT versions it supports are released.',
                                        ) !!}
                                    @else
                                        {!! __(
                                            'Select the date and time the mod will be published. If the mod is not published, it will not be discoverable by other users. Leave blank to keep the mod unpublished.',
                                        ) !!}
                                    @endif
                                </flux:description>

                                {{-- Pin to SPT version option (first) --}}
                                @if ($this->hasUnpublishedSptVersions())
                                    <div class="space-y-3">
                                        <label class="flex items-start gap-3">
                                            <flux:checkbox
                                                wire:model.live="pinToSptVersions"
                                                @change="if($event.target.checked) { $wire.set('publishedAt', null) }"
                                                class="mt-0.5"
                                            />
                                            <div class="flex-1">
                                                <div class="flex items-center flex-wrap gap-x-2 gap-y-1">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ __('Pin to unpublished SPT version publish dates:') }}
                                                    </span>

                                                    {{-- Show unpublished SPT versions inline --}}
                                                    @if (count($this->getUnpublishedSptVersions()) > 0)
                                                        @foreach ($this->getUnpublishedSptVersions() as $version)
                                                            <span
                                                                class="badge-version {{ $version['color_class'] }} inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium text-nowrap"
                                                            >
                                                                {{ $version['version'] }}
                                                            </span>
                                                        @endforeach
                                                    @endif
                                                </div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ __('When enabled, this mod version will automatically publish when the unpublished SPT versions it supports are released.') }}
                                                    <span
                                                        class="text-orange-600 dark:text-amber-400 font-medium">{{ __('Note: SPT versions can be released at any time, so only use this option if your mod version is fully ready for release.') }}</span>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    {{-- Separator (also hidden when pin is checked) --}}
                                    <div
                                        x-show="!pinToSpt"
                                        x-transition
                                    >
                                        <flux:separator
                                            text="or"
                                            class="my-4"
                                        />
                                    </div>
                                @endif

                                {{-- Manual publish date option (hidden when pin is checked) --}}
                                <div
                                    x-show="!pinToSpt"
                                    x-transition
                                >
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
                                                        class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors"
                                                    >edit your profile</a> to set a specific timezone.
                                                </flux:callout.text>
                                            </flux:callout>
                                        @else
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()->timezone]) }}
                                            </p>
                                        @endif
                                        <div class="flex gap-2 items-center">
                                            <flux:input
                                                type="datetime-local"
                                                wire:model.defer="publishedAt"
                                                placeholder="Leave blank to keep unpublished"
                                            />
                                            @if (auth()->user()->timezone !== null)
                                                <flux:button
                                                    size="sm"
                                                    variant="outline"
                                                    @click="$wire.set('publishedAt', now())"
                                                >Now</flux:button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <flux:error name="publishedAt" />
                            </flux:field>

                            <x-honeypot livewire-model="honeypotData" />

                        </div>
                    </div>
                    <div
                        class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button
                            variant="primary"
                            size="sm"
                            class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700"
                            type="submit"
                        >{{ __('Create Version') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
