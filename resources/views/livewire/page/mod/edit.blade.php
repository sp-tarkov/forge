<x-slot:title>
    {!! __('Edit Mod - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('Edit your mod details below.') !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
        {{ __('Edit Mod') }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Mod Information</h3>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">You may update your mod details using this form. Changes will be visible immediately after saving.</p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Please ensure you follow the <a href="#" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">community guidelines</a>
                        and the <a href="#" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">file submission guidelines</a>.
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
                                <flux:label>{{ __('Thumbnail') }}</flux:label>
                                <flux:description>{{ __('Optionally upload an image to use as the mod\'s thumbnail. This will be displayed on the mod page and in search results. The image should be square, JPG or PNG, and no larger than 2MB.') }}</flux:description>
                                <flux:input type="file" wire:model.blur="thumbnail" accept="image/*" />
                                <flux:error name="thumbnail" />
                                <div wire:loading wire:target="thumbnail" class="mt-2">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-cyan-500 h-2.5 rounded-full" style="width: 0%" wire:loading.class="animate-pulse"></div>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center gap-6">
                                    @if ($mod && $mod->thumbnail)
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Current Thumbnail:</p>
                                            <img src="{{ asset($mod->thumbnail) }}" class="h-20 w-20 object-cover rounded border border-gray-300 dark:border-gray-700" alt="Current thumbnail">
                                        </div>
                                    @endif
                                    @if ($thumbnail)
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">New Thumbnail:</p>
                                            <img src="{{ $thumbnail->temporaryUrl() }}" class="h-20 w-20 object-cover rounded border border-gray-300 dark:border-gray-700" alt="New thumbnail">
                                        </div>
                                        <div>
                                            <flux:button size="sm" variant="outline" wire:click="removeThumbnail" type="button">
                                                {{ __('Cancel Thumbnail Change') }}
                                            </flux:button>
                                        </div>
                                    @endif
                                </div>
                            </flux:field>

                            <flux:field class="col-span-6" x-data="{ count: $wire.name.length, text: $wire.name }">
                                <flux:label>{{ __('Name') }}</flux:label>
                                <flux:description>{{ __('Make it catchy, short, and sweet. Displayed on the mod page and in search results.') }}</flux:description>
                                <flux:input type="text" wire:model.blur="name" maxlength="75" x-model="text" @input="count = text.length" />
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`Max Length: ${count}/75`"></div>
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field class="col-span-6" x-data="{ count: $wire.guid.length, text: $wire.guid }">
                                <flux:label>{{ __('Mod GUID') }}</flux:label>
                                <flux:description>{{ __('A unique identifier for your mod in reverse domain notation (e.g., com.yourname.modname). This GUID should match the one in your mod files and will be used to identify your mod across different systems. Use only lowercase letters, numbers, and dots.') }}</flux:description>
                                <flux:input type="text" wire:model.blur="guid" maxlength="255" x-model="text" @input="count = text.length" placeholder="com.example.mymodname" />
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`Max Length: ${count}/255`"></div>
                                <flux:error name="guid" />
                            </flux:field>

                            <flux:field class="col-span-6" x-data="{ count: $wire.teaser.length, text: $wire.teaser }">
                                <flux:label>{{ __('Teaser') }}</flux:label>
                                <flux:description>{{ __('Describe the mod in a few words. This will be displayed on the mod card in search results and the top of the mod page.') }}</flux:description>
                                <flux:input type="text" wire:model.blur="teaser" maxlength="255" x-model="text" @input="count = text.length" />
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`Max Length: ${count}/255`"></div>
                                <flux:error name="teaser" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Description') }}</flux:label>
                                <flux:description>{{ __('Explain the mod in detail. This will be displayed on the mod page. Use markdown for formatting.') }}</flux:description>
                                <flux:textarea rows="6" wire:model.blur="description" placeholder="My mod is a *great mod* that does something..." />
                                <flux:error name="description" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('License') }}</flux:label>
                                <flux:description>{{ __('Choose which license your mod is released under. This will be displayed on the mod page.') }}</flux:description>
                                <flux:select wire:model.blur="license" placeholder="Choose license...">
                                    @foreach(\App\Models\License::orderBy('name')->get() as $license)
                                        <flux:select.option value="{{ $license->id }}">{{ $license->name }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="license" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Source Code Link') }}</flux:label>
                                <flux:description>{!! __('Provide a link to the source code for your mod. The source code for mods is required to be publicly available. This will be displayed on the mod page. We recommend using a service like <a href="https://github.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitHub</a> or <a href="https://gitlab.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitLab</a>.') !!}</flux:description>
                                <flux:input type="text" wire:model.blur="sourceCodeUrl" placeholder="https://github.com/username/mod-name" />
                                <flux:error name="sourceCodeUrl" />
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

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Disclosure">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="containsAiContent"
                                        label="Contains AI Content"
                                        description="This mod contains content that was generated by AI."
                                    />
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="containsAds"
                                        label="Contains Ads"
                                        description="This mod contains advertisements for products, services, or other content."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <x-honeypot livewire-model="honeypotData" />

                        </div>
                    </div>
                    <div class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button variant="primary" size="sm" class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">{{ __('Update Mod') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
