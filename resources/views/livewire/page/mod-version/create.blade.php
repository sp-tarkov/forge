<x-slot:title>
    {!! __('Create a New Mod Version - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('Create a new mod version to share with the community.') !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
        {{ __('Create a New Mod Version') }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">
        <flux:callout icon="exclamation-triangle" color="orange" inline="inline">
            <flux:callout.heading>Permanence Warning</flux:callout.heading>
            <flux:callout.text>
                Due to the Forge being in active development, mod versions created using this form may be removed at
                any time, for any reason. This form should only be used for testing. If you are creating a new version
                for a mod with the expectation that it remain on this site, please upload it to the Hub and wait for it
                to sync.
            </flux:callout.text>
        </flux:callout>
    </div>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Version Information</h3>
                    <p class="my-2 text-sm/6 text-gray-600 dark:text-gray-400">Add a new version to your mod by filling out this form. It will be unpublished by default.</p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Please ensure you follow the <a href="#" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">community guidelines</a>
                        and the <a href="#" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">file submission guidelines</a>.
                        Failing to do so will result in your mod being removed from the Forge and possible action being taken against your account.
                    </p>
                </div>
                <div class="px-4 sm:px-0"></div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="save">
                    <div class="px-4 py-5 bg-white dark:bg-gray-900 sm:p-6 shadow-sm sm:rounded-tl-md sm:rounded-tr-md">
                        <div class="grid grid-cols-6 gap-6">
                            @csrf

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Version Number') }}</flux:label>
                                <flux:description>{!! __('The version number for this release. Must follow semantic versioning.') !!}</flux:description>
                                <flux:input type="text" wire:model.blur="version" placeholder="1.0.0" maxlength="50" />
                                <flux:error name="version" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Description') }}</flux:label>
                                <flux:description>{{ __("Explain what's new or changed in this version. Use markdown for formatting.") }}</flux:description>
                                <flux:textarea rows="6" wire:model.blur="description" placeholder="This version includes updates to the..." />
                                <flux:error name="description" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Download Link') }}</flux:label>
                                <flux:description>{{ __('Provide a direct download link to the mod file. The mod archive must follow the structure specified in the file submission guidelines or the launcher will not support automatic installs or updates for your mod.') }}</flux:description>
                                <flux:input type="url" wire:model.blur="link" placeholder="https://www.example.com/your-mod-archive.7zip" />
                                <flux:error name="link" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('SPT Version Constraint') }}</flux:label>
                                <flux:description>{!! __('Specify which SPT versions this mod version is compatible with using semantic version constraints. For example, you can use the value ~3.11.0 to match all 3.11 versions. Works just like Composer or NPM. Start typing to see matches below.') !!}</flux:description>
                                <flux:input type="text" wire:model.live.debounce="sptVersionConstraint" placeholder="~3.11.0" />
                                <flux:error name="sptVersionConstraint" />
                                @if (count($matchingSptVersions) > 0)
                                    <div class="mt-2 space-y-1">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Current Matching SPT Versions:') }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($matchingSptVersions as $version)
                                                <span class="badge-version {{ $version['color_class'] }} inline-flex items-center rounded-md px-2 py-1 text-xs font-medium text-nowrap">
                                                    {{ $version['version'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('VirusTotal Link') }}</flux:label>
                                <flux:description>{!! __('Provide a link to the <a href="https://www.virustotal.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">VirusTotal</a> scan results for your mod files. This helps users verify the safety of your mod.') !!}</flux:description>
                                <flux:input type="url" wire:model.blur="virusTotalLink" placeholder="https://www.virustotal.com..." />
                                <flux:error name="virusTotalLink" />
                            </flux:field>

                            <flux:field class="col-span-6" x-data="{
                                now() {
                                    // Format: YYYY-MM-DDTHH:MM
                                    const pad = n => n.toString().padStart(2, '0');
                                    const d = new Date();
                                    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                                }
                            }">
                                <flux:label>{{ __('Publish Date') }}</flux:label>
                                <flux:description>
                                    {!! __('Select the date and time the mod will be published. If the mod is not published, it will not be discoverable by other users. Leave blank to keep the mod unpublished.') !!}
                                    @if (auth()->user()->timezone === null)
                                        <flux:callout icon="exclamation-triangle" color="orange" inline="inline" class="my-2">
                                            <flux:callout.text>
                                                You have not selected a timezone for your account. You may continue, but the published date will be interpreted as a UTC date. Alternatively, you can <a href="/user/profile" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">edit your profile</a> to set a specific timezone.
                                            </flux:callout.text>
                                        </flux:callout>
                                    @else
                                        {{ __('Your timezone is set to :timezone.', ['timezone' => auth()->user()->timezone]) }}
                                    @endif
                                </flux:description>
                                <div class="flex gap-2 items-center">
                                    <flux:input
                                        type="datetime-local"
                                        wire:model.defer="publishedAt"
                                    />
                                    @if (auth()->user()->timezone !== null)
                                        <flux:button size="sm" variant="outline" @click="$wire.set('publishedAt', now())">Now</flux:button>
                                    @endif
                                </div>
                                <flux:error name="publishedAt" />
                            </flux:field>

                            <x-honeypot livewire-model="honeypotData" />

                        </div>
                    </div>
                    <div class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button variant="primary" size="sm" class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">{{ __('Create Version') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
