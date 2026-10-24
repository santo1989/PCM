<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/" class="text-decoration-none">
                <x-application-logo light />
            </a>
        </x-slot>

        <div class="mb-3 small text-muted">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </div>

        <x-auth-session-status class="mb-3" :status="session('status')" />
        <x-auth-validation-errors class="mb-3" :errors="$errors" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-3">
                <x-label for="email" :value="__('Email')" />
                <x-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <div class="d-flex justify-content-end">
                <x-button>
                    {{ __('Email Password Reset Link') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
