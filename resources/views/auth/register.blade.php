<x-auth-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-app-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div>
                <x-label for="name" required :value="__('Name')" />
                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" placeholder="{{ __('Name') }}" required autofocus />
            </div>

            <div class="mt-4">
                <x-label for="email" required :value="__('Email')" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" placeholder="{{ __('Email') }}" required />
            </div>

            <div class="mt-4">
                <x-label for="password" required :value="__('Password')" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" placeholder="{{ __('Password') }}" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" required :value="__('Confirm Password')" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" placeholder="{{ __('Confirm Password') }}" required />
            </div>

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-button class="ml-4 bg-indigo-500 hover:bg-indigo-600 active:bg-indigo-400">
                    {{ __('Register') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-auth-layout>
