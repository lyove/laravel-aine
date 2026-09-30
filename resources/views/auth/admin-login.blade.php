<x-auth-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="{{ \App\Support\AdminPath::prefix() }}" class="block">
                <x-app-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
            <p class="mt-3 text-sm font-semibold text-gray-700 text-center">{{ __('Admin Area') }}</p>
        </x-slot>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf

            <div class="block">
                <x-label for="email" required :value="__('Email')" />
                <x-input
                    id="email"
                    class="block w-full mt-1"
                    type="email"
                    name="email"
                    :value="old('email')"
                    placeholder="{{ __('Email') }}"
                    required
                    autofocus
                />
            </div>

            <div class="block mt-4">
                <x-label for="password" required :value="__('Password')" />
                <x-input
                    id="password"
                    class="block w-full mt-1"
                    type="password"
                    name="password"
                    placeholder="{{ __('Password') }}"
                    required
                    autocomplete="current-password"
                />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="inline-flex items-center">
                    <input
                        id="remember_me"
                        type="checkbox"
                        class="border-gray-300 text-indigo-600 rounded-md shadow-sm cursor-pointer focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        name="remember"
                    >
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 pr-3 hover:text-gray-900" href="{{ route('admin.password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>

                <x-button class="bg-indigo-500 hover:bg-indigo-600 active:bg-indigo-400">
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-auth-layout>
