<x-form-section submit="updateProfileInformation">
    <x-slot:title>
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot:description>
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot:form>
        <!-- Profile Picture -->
        <div class="col-span-6 sm:col-span-4">
            <flux:file-upload wire:model="photo" label="{{ __('Profile Picture') }}">
                <flux:file-upload.dropzone
                    heading="{{ __('Drop image here or click to browse') }}"
                    text="{{ __('JPG or PNG, up to 1MB') }}"
                    with-progress
                    inline
                />
            </flux:file-upload>
            <flux:error name="photo" />
            @if ($photo)
                <flux:file-item
                    :heading="$photo->getClientOriginalName()"
                    :image="$photo->temporaryUrl()"
                    :size="$photo->getSize()"
                />
            @elseif ($this->user->profile_photo_path)
                <flux:file-item
                    heading="{{ __('Current Photo') }}"
                    :image="$this->user->profile_photo_url"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="deleteProfilePhoto" />
                    </x-slot>
                </flux:file-item>
            @endif
        </div>

        <!-- Cover Picture -->
        <div class="col-span-6 sm:col-span-4">
            <flux:file-upload wire:model="cover" label="{{ __('Cover Picture') }}">
                <flux:file-upload.dropzone
                    heading="{{ __('Drop image here or click to browse') }}"
                    text="{{ __('JPG or PNG, up to 4MB') }}"
                    with-progress
                    inline
                />
            </flux:file-upload>
            <flux:error name="cover" />
            @if ($cover)
                <flux:file-item
                    :heading="$cover->getClientOriginalName()"
                    :image="$cover->temporaryUrl()"
                    :size="$cover->getSize()"
                />
            @elseif ($this->user->cover_photo_path)
                <flux:file-item
                    heading="{{ __('Current Cover') }}"
                    :image="$this->user->cover_photo_url"
                >
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="deleteCoverPhoto" />
                    </x-slot>
                </flux:file-item>
            @endif
        </div>

        <!-- Name -->
        <div class="col-span-6 sm:col-span-4">
            <flux:field>
                <flux:label for="name">{{ __('Name') }}</flux:label>
                <flux:input
                    id="name"
                    type="text"
                    wire:model="state.name"
                    required
                    autocomplete="off"
                />
                <flux:error name="name" />
            </flux:field>
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <flux:field>
                <flux:label for="email">{{ __('Email') }}</flux:label>
                <flux:input
                    id="email"
                    type="email"
                    wire:model="state.email"
                    required
                    autocomplete="off"
                />
                <flux:error name="email" />
            </flux:field>

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) &&
                    !$this->user->hasVerifiedEmail())
                <p class="text-sm mt-2 text-red-600 dark:text-red-400">
                    {{ __('Your email address is unverified.') }}

                    <button
                        type="button"
                        class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                        wire:click.prevent="sendEmailVerification"
                    >
                        {{ __('Click here to re-send the verification email.') }}
                    </button>
                </p>

                @if ($this->verificationLinkSent)
                    <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                        {{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            @endif
        </div>

        <!-- Timezone -->
        <div class="col-span-6 sm:col-span-4">
            <flux:label for="timezone">{{ __('Timezone') }}</flux:label>
            <div class="flex items-center gap-2 mt-1">
                <flux:select
                    wire:model="state.timezone"
                    variant="combobox"
                    placeholder="{{ __('Select timezone...') }}"
                    required
                >
                    @foreach (\DateTimeZone::listIdentifiers() as $tz)
                        <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button
                    size="sm"
                    id="detect-timezone"
                >{{ __('Detect') }}</flux:button>
            </div>
            <flux:error name="state.timezone" />
        </div>

        <!-- About -->
        <div class="col-span-6 sm:col-span-4">
            <flux:label for="about">{{ __('About Me') }}</flux:label>
            <textarea
                id="about"
                rows="6"
                class="mt-1 block w-full rounded-md border-0 bg-white dark:bg-gray-700 py-2 px-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-gray-600 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm"
                wire:model.blur="state.about"
                placeholder="{{ __('Tell us about yourself. Use markdown for formatting.') }}"
            ></textarea>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('Basic markdown formatting is supported.') }}
            </p>
            <flux:error name="about" />
        </div>
    </x-slot>

    <x-slot name="actions">
        <flux:button
            variant="primary"
            wire:loading.attr="disabled"
            wire:target="photo,cover"
        >
            {{ __('Save') }}
        </flux:button>
    </x-slot>
</x-form-section>
