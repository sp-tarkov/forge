<x-form-section submit="updateProfileInformation">
    <x-slot:title>
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot:description>
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot:form>
        <!-- Profile Picture -->
        <div
            x-data="{ photoName: null, photoPreview: null }"
            class="col-span-6 sm:col-span-4"
        >
            <!-- Profile Photo File Input -->
            <input
                type="file"
                id="photo"
                class="hidden"
                wire:model.live="photo"
                x-ref="photo"
                x-on:change="
                        photoName = $refs.photo.files[0].name;
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            photoPreview = e.target.result;
                        };
                        reader.readAsDataURL($refs.photo.files[0]);
                    "
            />

            <x-label
                for="photo"
                value="{{ __('Profile Picture') }}"
            />

            <!-- Current Profile Photo -->
            <div
                class="mt-2"
                x-show="! photoPreview"
            >
                <img
                    src="{{ $this->user->profile_photo_url }}"
                    alt="{{ $this->user->name }}"
                    class="rounded-full h-20 w-20 object-cover"
                >
            </div>

            <!-- New Profile Photo Preview -->
            <div
                class="mt-2"
                x-show="photoPreview"
                style="display: none;"
            >
                <span
                    class="block rounded-full w-20 h-20 bg-cover bg-no-repeat bg-center"
                    x-bind:style="'background-image: url(\'' + photoPreview + '\');'"
                >
                </span>
            </div>

            <x-secondary-button
                class="mt-2 me-2"
                type="button"
                x-on:click.prevent="$refs.photo.click()"
            >
                {{ __('Select A New Photo') }}
            </x-secondary-button>

            @if ($this->user->profile_photo_path)
                <x-secondary-button
                    type="button"
                    class="mt-2"
                    wire:click="deleteProfilePhoto"
                >
                    {{ __('Remove Photo') }}
                </x-secondary-button>
            @endif

            <x-input-error
                for="photo"
                class="mt-2"
            />
        </div>

        <!-- Cover Picture -->
        <div
            x-data="{ coverName: null, coverPreview: null }"
            class="col-span-6 sm:col-span-4"
        >
            <!-- Cover Picture File Input -->
            <input
                type="file"
                id="cover"
                class="hidden"
                wire:model.live="cover"
                x-ref="cover"
                x-on:change="
                    coverName = $refs.cover.files[0].name;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        coverPreview = e.target.result;
                    };
                    reader.readAsDataURL($refs.cover.files[0]);
                "
            />

            <x-label
                for="cover"
                value="{{ __('Cover Picture') }}"
            />

            <!-- Current Cover Photo -->
            <div
                class="mt-2"
                x-show="! coverPreview"
            >
                <img
                    src="{{ $this->user->cover_photo_url }}"
                    alt="{{ $this->user->name }}"
                    class="rounded-xs h-20 w-60 object-cover"
                >
            </div>

            <!-- New Cover Photo Preview -->
            <div
                class="mt-2"
                x-show="coverPreview"
                style="display: none;"
            >
                <span
                    class="block h-20 w-60 bg-cover bg-no-repeat bg-center"
                    x-bind:style="'background-image: url(\'' + coverPreview + '\');'"
                >
                </span>
            </div>

            <x-secondary-button
                class="mt-2 me-2"
                type="button"
                x-on:click.prevent="$refs.cover.click()"
            >
                {{ __('Select A New Cover Photo') }}
            </x-secondary-button>

            @if ($this->user->cover_photo_path)
                <x-secondary-button
                    type="button"
                    class="mt-2"
                    wire:click="deleteCoverPhoto"
                >
                    {{ __('Remove Cover Photo') }}
                </x-secondary-button>
            @endif

            <x-input-error
                for="cover"
                class="mt-2"
            />
        </div>

        <!-- Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label
                for="name"
                value="{{ __('Name') }}"
            />
            <x-input
                id="name"
                type="text"
                class="mt-1 block w-full"
                wire:model="state.name"
                required
                autocomplete="off"
            />
            <x-input-error
                for="name"
                class="mt-2"
            />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-label
                for="email"
                value="{{ __('Email') }}"
            />
            <x-input
                id="email"
                type="email"
                class="mt-1 block w-full"
                wire:model="state.email"
                required
                autocomplete="off"
            />
            <x-input-error
                for="email"
                class="mt-2"
            />

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
            <x-label
                for="timezone"
                value="{{ __('Timezone') }}"
            />
            <div class="flex items-center gap-2 mt-1">
                <select
                    id="timezone"
                    class="block w-full rounded-md border-0 bg-white dark:bg-gray-700 py-2 px-3 text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 focus:ring-2 focus:ring-inset focus:ring-gray-600 dark:focus:bg-gray-200 dark:focus:text-black dark:focus:ring-0 sm:text-sm"
                    wire:model="state.timezone"
                    required
                >
                    @if ($this->user->timezone === null)
                        <option
                            value=""
                            selected
                        ></option>
                    @endif
                    @foreach (\DateTimeZone::listIdentifiers() as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </select>

                <flux:button
                    size="sm"
                    id="detect-timezone"
                >{{ __('Detect') }}</flux:button>
            </div>
            <x-input-error
                for="timezone"
                class="mt-2"
            />
        </div>

        <!-- About -->
        <div class="col-span-6 sm:col-span-4">
            <x-label
                for="about"
                value="{{ __('About Me') }}"
            />
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
            <x-input-error
                for="about"
                class="mt-2"
            />
        </div>
    </x-slot>

    <x-slot name="actions">
        <x-action-message
            class="me-3"
            on="saved"
        >
            {{ __('Saved.') }}
        </x-action-message>

        <x-button
            wire:loading.attr="disabled"
            wire:target="photo,cover"
        >
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
