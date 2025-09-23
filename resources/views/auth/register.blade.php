<x-layouts.base variant="simple">
    <x-slot:title>
        {{ __('Register an account') }}
    </x-slot>

    <x-slot:description>
        {{ __('Register an account to start using The Forge and join the community.') }}
    </x-slot>

    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            {{-- Input Fields Group --}}
            <div class="space-y-4">
                <flux:field>
                    <flux:label for="name">{{ __('Name') }}</flux:label>
                    <flux:input 
                        id="name" 
                        type="text" 
                        name="name" 
                        :value="old('name')" 
                        required 
                        autofocus 
                        autocomplete="name"
                        placeholder="Enter your username" 
                    />
                </flux:field>

                <flux:field>
                    <flux:label for="email">{{ __('Email') }}</flux:label>
                    <flux:input 
                        id="email" 
                        type="email" 
                        name="email" 
                        :value="old('email')" 
                        required 
                        autocomplete="username"
                        placeholder="your@email.com"
                    />
                </flux:field>

                <flux:field>
                    <flux:label for="password">{{ __('Password') }}</flux:label>
                    <flux:input 
                        id="password" 
                        type="password" 
                        name="password" 
                        required 
                        autocomplete="new-password"
                        placeholder="Enter a secure password"
                    />
                </flux:field>

                <flux:field>
                    <flux:label for="password_confirmation">{{ __('Confirm Password') }}</flux:label>
                    <flux:input 
                        id="password_confirmation" 
                        type="password" 
                        name="password_confirmation" 
                        required 
                        autocomplete="new-password"
                        placeholder="Re-enter your password"
                    />
                </flux:field>

                <flux:field>
                    <flux:label for="timezone">{{ __('Timezone') }}</flux:label>
                    <select 
                        id="timezone" 
                        name="timezone" 
                        data-tz-auto="true"
                        required
                        class="flux-input block w-full rounded-md border-0 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-gray-500 focus:bg-white/10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white/20"
                    >
                        <option value="" @selected(!old('timezone'))>{{ __('Select your timezone') }}</option>
                        @foreach (\DateTimeZone::listIdentifiers() as $tz)
                            <option value="{{ $tz }}" @selected(old('timezone') === $tz)>
                                {{ $tz }}
                            </option>
                        @endforeach
                    </select>
                </flux:field>
            </div>

            {{-- Terms and Conditions --}}
            <div class="mt-6" x-data="{ checked: false }">
                <flux:field>
                    <div class="flex items-start">
                        <input 
                            type="checkbox"
                            name="terms" 
                            id="terms"
                            required 
                            class="mt-0.5 rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            x-model="checked"
                            @change="$dispatch('terms-changed', { accepted: checked })"
                        />
                        <label for="terms" class="ms-2">
                            <span class="text-sm text-gray-300 dark:text-gray-400">
                                {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                    'terms_of_service' => '<a target="_blank" href="'.route('static.terms').'" class="underline text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-gray-200 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">'.__('Terms of Service').'</a>',
                                    'privacy_policy' => '<a target="_blank" href="'.route('static.privacy').'" class="underline text-gray-300 dark:text-gray-400 hover:text-white dark:hover:text-gray-200 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">'.__('Privacy Policy').'</a>',
                                ]) !!}
                            </span>
                        </label>
                    </div>
                </flux:field>
            </div>

            {{-- Submit Button and Already Registered Link --}}
            <div class="mt-6 space-y-4">
                <x-honeypot />
                
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Register') }}
                </flux:button>

                <div class="text-center">
                    <a class="text-sm text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-gray-300 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500" href="{{ route('login') }}" wire:navigate>
                        {{ __('Already registered?') }}
                    </a>
                </div>
            </div>
        </form>

        {{-- Discord Registration Option --}}
        @if (config('services.discord.client_id') && config('services.discord.client_secret'))
            <div class="mt-6">
                <flux:separator variant="subtle" class="text-gray-500">
                    {{ __('Or continue with') }}
                </flux:separator>

                <div class="mt-6" 
                     x-data="{ termsAccepted: false }"
                     @terms-changed.window="termsAccepted = $event.detail.accepted">
                    <flux:tooltip>
                        <a 
                            x-bind:href="termsAccepted ? '{{ route('login.socialite', ['provider' => 'discord']) }}' : '#'"
                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-[#5865F2] hover:bg-[#5865F2]/90 text-white font-medium rounded-lg transition-colors"
                            x-bind:class="termsAccepted ? '' : 'opacity-50 cursor-not-allowed'"
                            x-on:click="if(!termsAccepted) { $event.preventDefault(); }"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-4 h-4 mr-2" viewBox="0 0 16 16">
                                <path d="M13.545 2.907a13.2 13.2 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.2 12.2 0 0 0-3.658 0 8 8 0 0 0-.412-.833.05.05 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.04.04 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032q.003.022.021.037a13.3 13.3 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019q.463-.63.818-1.329a.05.05 0 0 0-.01-.059l-.018-.011a9 9 0 0 1-1.248-.595.05.05 0 0 1-.02-.066l.015-.019q.127-.095.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.05.05 0 0 1 .053.007q.121.1.248.195a.05.05 0 0 1-.004.085 8 8 0 0 1-1.249.594.05.05 0 0 0-.03.03.05.05 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.2 13.2 0 0 0 4.001-2.02.05.05 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.03.03 0 0 0-.02-.019m-8.198 7.307c-.789 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612m5.316 0c-.788 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612"/>
                            </svg>
                            {{ __('Register with Discord') }}
                        </a>
                        <flux:tooltip.content x-show="!termsAccepted">
                            Please accept the Terms of Service and Privacy Policy first
                        </flux:tooltip.content>
                    </flux:tooltip>
                </div>
            </div>
        @endif
    </x-authentication-card>
</x-layouts.base>