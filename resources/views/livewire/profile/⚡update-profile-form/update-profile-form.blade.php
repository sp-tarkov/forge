<x-form-section submit="updateProfileInformation">
    <x-slot:title>
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot:description>
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot:form>
        {{-- Profile Picture --}}
        <flux:field class="col-span-6">
            <flux:label>{{ __('Profile Picture') }}</flux:label>
            <flux:description>
                {{ __('A square image used as your avatar across the site. Animated images stay animated. JPG, PNG, GIF, WebP, or AVIF, up to 1MB.') }}
            </flux:description>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @if ($photo)
                    <div class="flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 p-3">
                        @if ($photo->isPreviewable())
                            <img
                                src="{{ $photo->temporaryUrl() }}"
                                class="size-20 shrink-0 rounded-lg object-cover"
                                alt="{{ __('Profile picture preview') }}"
                            >
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-300">{{ $photo->getClientOriginalName() }}
                            </p>
                            <p class="mt-0.5 text-xs text-zinc-400">{{ Number::fileSize($photo->getSize(), 1) }}</p>
                        </div>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="x-mark"
                            wire:click="removePhoto"
                            type="button"
                        />
                    </div>
                @elseif ($this->user->profile_photo_path)
                    <div class="flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 p-3">
                        <img
                            src="{{ $this->user->profile_photo_url }}"
                            class="size-20 shrink-0 rounded-lg object-cover"
                            alt="{{ __('Current profile picture') }}"
                        >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-zinc-300">{{ __('Current Picture') }}</p>
                            <p class="mt-0.5 text-xs text-zinc-400">
                                {{ __('Upload a new image to replace, or delete the current one.') }}</p>
                        </div>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="trash"
                            wire:click="deleteProfilePhoto"
                            wire:confirm="{{ __('Are you sure you want to delete your current profile picture?') }}"
                            type="button"
                        />
                    </div>
                @endif
                <x-image-crop-upload
                    wire-model="photo"
                    max-kilobytes="1024"
                    preserve-animation
                    crop-model="photoCropRect"
                    :heading="__('Drop image here or click to browse')"
                    :text="__('JPG, PNG, GIF, WebP, or AVIF, up to 1MB')"
                    @class([
                        'h-full',
                        'lg:col-span-2' => !$photo && !$this->user->profile_photo_path,
                    ])
                />
            </div>
            <flux:error name="photo" />
        </flux:field>

        {{-- Cover Picture --}}
        <flux:field class="col-span-6">
            <flux:label>{{ __('Cover Picture') }}</flux:label>
            <flux:description>
                {{ __('A wide banner image displayed at the top of your profile. JPG, PNG, GIF, WebP, or AVIF, up to 2MB.') }}
            </flux:description>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @if ($cover)
                    <div class="flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 p-3">
                        @if ($cover->isPreviewable())
                            <img
                                src="{{ $cover->temporaryUrl() }}"
                                class="size-20 shrink-0 rounded-lg object-cover"
                                alt="{{ __('Cover picture preview') }}"
                            >
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-zinc-300">{{ $cover->getClientOriginalName() }}
                            </p>
                            <p class="mt-0.5 text-xs text-zinc-400">{{ Number::fileSize($cover->getSize(), 1) }}</p>
                        </div>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="x-mark"
                            wire:click="removeCover"
                            type="button"
                        />
                    </div>
                @elseif ($this->user->cover_photo_path)
                    <div class="flex items-center gap-4 rounded-xl border border-white/10 bg-white/5 p-3">
                        <img
                            src="{{ $this->user->cover_photo_url }}"
                            class="size-20 shrink-0 rounded-lg object-cover"
                            alt="{{ __('Current cover picture') }}"
                        >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-zinc-300">{{ __('Current Cover') }}</p>
                            <p class="mt-0.5 text-xs text-zinc-400">
                                {{ __('Upload a new image to replace, or delete the current one.') }}</p>
                        </div>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="trash"
                            wire:click="deleteCoverPhoto"
                            wire:confirm="{{ __('Are you sure you want to delete your current cover picture?') }}"
                            type="button"
                        />
                    </div>
                @endif
                <flux:file-upload
                    wire:model="cover"
                    @class([
                        'h-full',
                        'lg:col-span-2' => !$cover && !$this->user->cover_photo_path,
                    ])
                >
                    <flux:file-upload.dropzone
                        heading="{{ __('Drop image here or click to browse') }}"
                        text="{{ __('JPG, PNG, GIF, WebP, or AVIF, up to 2MB') }}"
                        with-progress
                        inline
                        class="h-full"
                    />
                </flux:file-upload>
            </div>
            <flux:error name="cover" />
        </flux:field>

        {{-- Name --}}
        <flux:field
            class="col-span-6"
            x-data="{ count: $wire.state.name?.length ?? 0, text: $wire.state.name ?? '' }"
        >
            <flux:label>{{ __('Name') }}</flux:label>
            <flux:description>
                {{ __('Your display name. Shown on your profile, comments, and anywhere you appear across the site.') }}
            </flux:description>
            <flux:input
                id="name"
                type="text"
                wire:model="state.name"
                maxlength="255"
                autocomplete="off"
                required
                x-model="text"
                @input="count = text.length"
            />
            <div
                class="mt-1 text-sm text-gray-400"
                x-text="`Max Length: ${count}/255`"
            ></div>
            <flux:error name="name" />
        </flux:field>

        {{-- Email --}}
        <flux:field class="col-span-6">
            <flux:label>{{ __('Email') }}</flux:label>
            <flux:description>
                {{ __('Used for account sign-in and email notifications. Changing this address will require you to verify it again.') }}
            </flux:description>
            <flux:input
                id="email"
                type="email"
                wire:model="state.email"
                maxlength="255"
                autocomplete="off"
                required
            />
            <flux:error name="email" />

            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) &&
                    !$this->user->hasVerifiedEmail())
                <flux:callout
                    icon="exclamation-triangle"
                    color="orange"
                    inline
                    class="mt-3"
                >
                    <flux:callout.text>
                        {{ __('Your email address is unverified.') }}
                        <button
                            type="button"
                            class="text-white underline transition-colors hover:text-cyan-200"
                            wire:click.prevent="sendEmailVerification"
                        >
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </flux:callout.text>
                </flux:callout>

                @if ($this->verificationLinkSent)
                    <flux:callout
                        icon="check-circle"
                        color="green"
                        inline
                        class="mt-3"
                    >
                        <flux:callout.text>
                            {{ __('A new verification link has been sent to your email address.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endif
            @endif
        </flux:field>

        {{-- Timezone --}}
        <flux:field class="col-span-6">
            <flux:label>{{ __('Timezone') }}</flux:label>
            <flux:description>
                {{ __('Used to display dates and times in your local timezone across the site.') }}
            </flux:description>
            <div class="flex items-center gap-2">
                <flux:select
                    wire:model="state.timezone"
                    variant="listbox"
                    searchable
                    placeholder="{{ __('Select timezone...') }}"
                    class="flex-1"
                    required
                >
                    @foreach (\DateTimeZone::listIdentifiers() as $tz)
                        <flux:select.option value="{{ $tz }}">{{ $tz }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button
                    size="sm"
                    variant="outline"
                    id="detect-timezone"
                    type="button"
                >{{ __('Detect') }}</flux:button>
            </div>
            <flux:error name="state.timezone" />
        </flux:field>

        {{-- About --}}
        <flux:field class="col-span-6">
            <x-markdown-editor
                wire-model="state.about"
                name="about"
                :label="__('About Me')"
                :description="__(
                    'Tell other users a little about yourself. Shown on your public profile. Supports markdown formatting.',
                )"
                :placeholder="__('Tell us about yourself...')"
                rows="6"
                purify-config="comments"
                maxlength="500"
            />
        </flux:field>
    </x-slot>

    <x-slot name="actions">
        <flux:button
            type="submit"
            variant="primary"
            size="sm"
            class="my-1.5 bg-cyan-700 text-white hover:bg-cyan-600"
            wire:loading.attr="disabled"
            wire:target="photo,cover"
        >
            {{ __('Save') }}
        </flux:button>
    </x-slot>
</x-form-section>
