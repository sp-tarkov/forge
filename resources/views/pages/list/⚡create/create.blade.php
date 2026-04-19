<x-slot:title>{{ __('Create List - The Forge') }}</x-slot>

<x-slot:header></x-slot>

<div class="mx-auto max-w-3xl px-2 sm:px-4 lg:px-8 py-6">
    <div class="p-4 sm:p-6 bg-white dark:bg-gray-950 rounded-xl shadow-md dark:shadow-gray-950 drop-shadow-2xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ __('Create a new list') }}</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ __('Group mods together by theme, compatibility, or personal taste.') }}
            </p>
        </div>

        <x-list.config-guidance class="mb-6" />

        <form
            wire:submit="save"
            class="space-y-4"
        >
            <flux:input
                wire:model="form.title"
                :label="__('Title')"
                :placeholder="__('e.g., My Favourite QoL Mods')"
                required
                maxlength="{{ config('mod-lists.validation.title_max') }}"
            />

            <flux:textarea
                wire:model="form.description"
                :label="__('Description')"
                :description="__('Markdown is supported.')"
                rows="6"
                maxlength="{{ config('mod-lists.validation.description_max') }}"
            />

            <flux:select
                wire:model="form.visibility"
                :label="__('Visibility')"
            >
                @foreach (\App\Enums\ListVisibility::cases() as $visibility)
                    <flux:select.option value="{{ $visibility->value }}">
                        {{ __($visibility->label()) }}: {{ __($visibility->description()) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model="form.spt_version_id"
                :label="__('Target SPT version (optional)')"
                :description="__('If set, list items will show the version compatible with this SPT release.')"
            >
                <flux:select.option value="">{{ __('Latest compatible version') }}</flux:select.option>
                @foreach ($form->availableSptVersions() as $version)
                    <flux:select.option value="{{ $version->id }}">
                        {{ $version->version }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex items-center justify-end gap-3 pt-2">
                <flux:button
                    variant="ghost"
                    :href="route('list.index')"
                    wire:navigate
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    type="submit"
                    variant="primary"
                >
                    {{ __('Create list') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
