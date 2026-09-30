<x-auth-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-app-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="block">
                <x-label for="email" required :value="__('Email')" />
                <x-input
                    id="email"
                    class="block mt-1 w-full"
                    type="email"
                    name="email"
                    :value="old('email', $request->email)"
                    placeholder="{{ __('Email') }}"
                    required
                    autofocus
                />
            </div>

            <div class="mt-4">
                <x-label for="password" required :value="__('Password')" />
                <x-input 
                    id="password" 
                    class="block mt-1 w-full" 
                    type="password" 
                    name="password" 
                    placeholder="{{ __('Password') }}" 
                    required 
                />
            </div>

            <div class="mt-4">
                <x-label for="password_confirmation" required :value="__('Confirm Password')" />

                <x-input 
                    id="password_confirmation" 
                    class="block mt-1 w-full"
                    type="password"
                    name="password_confirmation" 
                    placeholder="{{ __('Confirm Password') }}" 
                    required 
                />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-button class="bg-indigo-500 hover:bg-indigo-600 active:bg-indigo-400">
                    {{ __('Reset Password') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-auth-layout>
