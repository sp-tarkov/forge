<x-layouts.base variant="simple">
    <x-slot:title>
        {{ __('Sign into your account') }}
    </x-slot>

    <x-slot:description>
        {{ __('Sign into your Forge account to access your mods, comments, and more.') }}
    </x-slot>

    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                {{ $value }}
            </div>
        @endsession

        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- Input Fields Group --}}
            <div class="space-y-4">
                <flux:field>
                    <flux:label for="email">{{ __('Email') }}</flux:label>
                    <flux:input 
                        id="email" 
                        type="email" 
                        name="email" 
                        :value="old('email')" 
                        required 
                        autofocus 
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
                        autocomplete="current-password"
                        placeholder="Enter your password"
                    />
                </flux:field>
            </div>

            {{-- Remember Me and Links --}}
            <div class="mt-6 flex items-center justify-between">
                <div class="flex items-center">
                    <input 
                        type="checkbox"
                        id="remember_me" 
                        name="remember"
                        class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    />
                    <label for="remember_me" class="ms-2 text-sm text-gray-400 dark:text-gray-500">
                        {{ __('Remember me') }}
                    </label>
                </div>
                <div class="flex items-center space-x-3">
                    <a class="text-sm text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-gray-300 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                       href="{{ route('register') }}">
                        {{ __('Register') }}
                    </a>
                    <a class="text-sm text-gray-400 dark:text-gray-500 hover:text-white dark:hover:text-gray-300 underline rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                       href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                </div>
            </div>

            {{-- Submit Button --}}
            <div class="mt-6">
                <x-honeypot />
                
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        {{-- Discord Login Option --}}
        @if (config('services.discord.client_id') && config('services.discord.client_secret'))
            <div class="mt-6">
                <flux:separator variant="subtle" class="text-gray-400 dark:text-gray-500">
                    {{ __('Or continue with') }}
                </flux:separator>

                <div class="mt-6">
                    <a 
                        href="{{ route('login.socialite', ['provider' => 'discord']) }}"
                        class="w-full inline-flex items-center justify-center px-4 py-2 bg-[#5865F2] hover:bg-[#5865F2]/90 text-white font-medium rounded-lg transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-4 h-4 mr-2" viewBox="0 0 16 16">
                            <path d="M13.545 2.907a13.2 13.2 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.2 12.2 0 0 0-3.658 0 8 8 0 0 0-.412-.833.05.05 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.04.04 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032q.003.022.021.037a13.3 13.3 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019q.463-.63.818-1.329a.05.05 0 0 0-.01-.059l-.018-.011a9 9 0 0 1-1.248-.595.05.05 0 0 1-.02-.066l.015-.019q.127-.095.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.05.05 0 0 1 .053.007q.121.1.248.195a.05.05 0 0 1-.004.085 8 8 0 0 1-1.249.594.05.05 0 0 0-.03.03.05.05 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.2 13.2 0 0 0 4.001-2.02.05.05 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.03.03 0 0 0-.02-.019m-8.198 7.307c-.789 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612m5.316 0c-.788 0-1.438-.724-1.438-1.612s.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612"/>
                        </svg>
                        {{ __('Login with Discord') }}
                    </a>
                </div>
            </div>
        @endif
    </x-authentication-card>
</x-layouts.base>