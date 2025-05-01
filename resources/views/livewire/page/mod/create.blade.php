<div>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
            {{ __('Create a New Mod') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Mod Information</h3>
                    <p class="my-2 text-sm/6 text-gray-600 dark:text-gray-400">Add your mod to the Forge by filling out this form. After the mod has been created, you will be able to submit mod versions/files with additional information.</p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Please ensure you follow the <a href="#" target="_blank" class="text-cyan-500 dark:text-cyan-400 hover:text-cyan-600 dark:hover:text-cyan-500">community guidelines</a>
                        and the <a href="#" target="_blank" class="text-cyan-500 dark:text-cyan-400 hover:text-cyan-600 dark:hover:text-cyan-500">file submission guidelines</a>.
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
                                <flux:label>{{ __('Avatar') }}</flux:label>
                                <flux:description>{{ __('Optionally upload an image to use as the mod\'s avatar. This will be displayed on the mod page and in search results. The image should be square, JPG or PNG, and no larger than 2MB.') }}</flux:description>
                                <flux:input type="file" wire:model.blur="avatar" accept="image/*" />
                                <flux:error name="avatar" />
                                <div wire:loading wire:target="avatar" class="mt-2">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-cyan-500 h-2.5 rounded-full" style="width: 0%" wire:loading.class="animate-pulse"></div>
                                    </div>
                                </div>
                                @if ($avatar)
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Preview:</p>
                                        <img src="{{ $avatar->temporaryUrl() }}" class="h-20 w-20 object-cover rounded" alt="Avatar preview">
                                    </div>
                                @endif
                            </flux:field>

                            <flux:field class="col-span-6" x-data="{ count: 0, text: '' }">
                                <flux:label>{{ __('Name') }}</flux:label>
                                <flux:description>{{ __('Make it catchy, short, and sweet. Displayed on the mod page and in search results.') }}</flux:description>
                                <flux:input type="text" wire:model.blur="name" maxlength="75" x-model="text" @input="count = text.length" />
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`Max Length: ${count}/75`"></div>
                                <flux:error name="name" />
                            </flux:field>

                            <flux:field class="col-span-6" x-data="{ count: 0, text: '' }">
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
                                <flux:description>{!! __('Provide a link to the source code for your mod. The source code for mods is required to be publicly available. This will be displayed on the mod page. We recommend using a service like <a href="https://github.com" target="_blank">GitHub</a> or <a href="https://gitlab.com" target="_blank">GitLab</a>.') !!}</flux:description>
                                <flux:input type="text" wire:model.blur="sourceCodeUrl" placeholder="https://github.com/username/mod-name" />
                                <flux:error name="sourceCodeUrl" />
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

                        </div>
                    </div>
                    <div class="flex items-center justify-end px-4 py-3 bg-gray-50 dark:bg-gray-900 border-t-2 border-transparent dark:border-t-gray-700 text-end sm:px-6 shadow-sm sm:rounded-bl-md sm:rounded-br-md gap-4">
                        <flux:button variant="primary" size="sm" class="my-1.5 text-black dark:text-white hover:bg-cyan-400 dark:hover:bg-cyan-600 bg-cyan-500 dark:bg-cyan-700" type="submit">{{ __('Create Mod') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
