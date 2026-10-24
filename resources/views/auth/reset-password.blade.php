<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/" class="text-decoration-none">
                <x-application-logo light />
            </a>
        </x-slot>

        <x-auth-validation-errors class="mb-3" :errors="$errors" />

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="mb-3">
                <x-label for="email" :value="__('Email')" />
                <x-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus />
            </div>

            <div class="mb-3">
                <x-label for="password" :value="__('Password')" />
                <x-input id="password" type="password" name="password" required />
            </div>

            <div class="mb-3">
                <x-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-input id="password_confirmation" type="password" name="password_confirmation" required />
            </div>

            <div class="d-flex justify-content-end">
                <x-button>
                    {{ __('Reset Password') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
