<x-slot:title>
    {!! __('Create a New Mod - The Forge') !!}
</x-slot>

<x-slot:description>
    {!! __('Create a new mod to share with the community.') !!}
</x-slot>

<x-slot:header>
    <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
        {{ __('Create a New Mod') }}
    </h2>
</x-slot>

<div>
    <div class="max-w-7xl mx-auto pb-6 px-4 gap-6 sm:px-6 lg:px-8">
        <flux:callout icon="exclamation-triangle" color="orange" inline="inline">
            <flux:callout.heading>Permanence Warning</flux:callout.heading>
            <flux:callout.text>
                Due to the Forge being in active development, mods created using this form may be removed at any time,
                for any reason. This form should only be used for testing. If you are creating a new mod with the
                expectation that it remain on this site, please upload it to the Hub and wait for it to sync.
            </flux:callout.text>
        </flux:callout>
    </div>

    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1 flex justify-between">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Mod Information</h3>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">Add your mod to the Forge by filling out this form. After the mod has been created, you will be able to submit mod versions/files with additional information.</p>
                    <p class="my-2 text-sm/6 text-sm text-gray-600 dark:text-gray-400">
                        Please ensure you follow the <a href="{{ route('static.community-standards') }}" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">Community Standards</a>
                        and the <a href="{{ route('static.content-guidelines') }}" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">Content Guidelines</a>.
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
                                <flux:label>{{ __('Thumbnail') }}</flux:label>
                                <flux:description>{{ __('Optionally upload an image to use as the mod\'s thumbnail. This will be displayed on the mod page and in search results. The image should be square, JPG or PNG, and no larger than 2MB.') }}</flux:description>
                                <flux:input type="file" wire:model.blur="thumbnail" accept="image/*" />
                                <flux:error name="thumbnail" />
                                <div wire:loading wire:target="thumbnail" class="mt-2">
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-cyan-500 h-2.5 rounded-full" style="width: 0%" wire:loading.class="animate-pulse"></div>
                                    </div>
                                </div>
                                @if ($thumbnail)
                                    <div class="mt-2 flex items-center gap-2">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Preview:</p>
                                            <img src="{{ $thumbnail->temporaryUrl() }}" class="h-20 w-20 object-cover rounded" alt="Thumbnail preview">
                                        </div>
                                        <flux:button size="sm" variant="outline" wire:click="removeThumbnail" type="button">
                                            {{ __('Remove Thumbnail') }}
                                        </flux:button>
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
                                <flux:label>{{ __('Mod GUID') }}</flux:label>
                                <flux:description>{{ __('A unique identifier for your mod in reverse domain notation. This GUID should match the one in your mod files and will be used to identify your mod across different systems. Use only lowercase letters, numbers, and dots.') }}</flux:description>
                                <flux:input type="text" wire:model.blur="guid" maxlength="255" x-model="text" @input="count = text.length" placeholder="com.username.modname" />
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`Max Length: ${count}/255`"></div>
                                <flux:error name="guid" />
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
                                <flux:label>{{ __('Category') }}</flux:label>
                                <flux:description>{{ __('Select the category that best describes your mod. This helps users find your mod more easily.') }}</flux:description>
                                <flux:select wire:model.blur="category" placeholder="Choose category...">
                                    @foreach(\App\Models\ModCategory::whereNull('parent_category_id')->orderBy('show_order')->orderBy('title')->get() as $category)
                                        <optgroup label="{{ $category->title }}">
                                            <flux:select.option value="{{ $category->id }}">{{ $category->title }}</flux:select.option>
                                            @foreach($category->children as $childCategory)
                                                <flux:select.option value="{{ $childCategory->id }}">{{ $childCategory->title }}</flux:select.option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </flux:select>
                                <flux:error name="category" />
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:label>{{ __('Source Code Links') }}</flux:label>
                                <flux:description>{!! __('Provide links to the source code for your mod. The source code for mods is required to be publicly available. You can add up to 4 links (e.g., main repository, mirror, documentation). We recommend using services like <a href="https://github.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitHub</a> or <a href="https://gitlab.com" target="_blank" class="underline text-black dark:text-white hover:text-cyan-800 hover:dark:text-cyan-200 transition-colors">GitLab</a>.') !!}</flux:description>

                                <div class="space-y-3">
                                    @foreach($sourceCodeLinks as $index => $link)
                                        <div class="flex gap-2 items-center">
                                            <div class="flex-1">
                                                <flux:input
                                                    type="url"
                                                    wire:model.blur="sourceCodeLinks.{{ $index }}.url"
                                                    placeholder="https://github.com/username/mod-name"
                                                />
                                            </div>
                                            <div class="w-40">
                                                <flux:input
                                                    type="text"
                                                    wire:model.blur="sourceCodeLinks.{{ $index }}.label"
                                                    placeholder="Label (optional)"
                                                />
                                            </div>
                                            @if(count($sourceCodeLinks) > 1)
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="removeSourceCodeLink({{ $index }})"
                                                    type="button"
                                                    icon="x-mark"
                                                />
                                            @endif
                                        </div>
                                        @error('sourceCodeLinks.' . $index . '.url')
                                            <flux:error>{{ $message }}</flux:error>
                                        @enderror
                                    @endforeach

                                    @if(count($sourceCodeLinks) < 4)
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="addSourceCodeLink"
                                            type="button"
                                            icon="plus"
                                        >
                                            {{ __('Add another link') }}
                                        </flux:button>
                                    @endif
                                </div>

                                <flux:error name="sourceCodeLinks" />
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

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Comments">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="commentsDisabled"
                                        label="Disable Comments"
                                        description="When enabled, normal users will not be able to view or create comments on this mod. Administrators and moderators will still have full access."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <flux:field class="col-span-6">
                                <flux:checkbox.group label="Notifications">
                                    <flux:checkbox
                                        value="true"
                                        wire:model.blur="subscribeToComments"
                                        label="Subscribe to Comment Notifications"
                                        description="When enabled, you will receive notifications when users comment on this mod. You can unsubscribe later from individual notifications."
                                    />
                                </flux:checkbox.group>
                            </flux:field>

                            <x-honeypot livewire-model="honeypotData" />

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
