<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/" class="text-decoration-none">
                <x-application-logo light />
            </a>
        </x-slot>

        <x-auth-session-status class="mb-3" :status="session('status')" />
        <x-auth-validation-errors class="mb-3" :errors="$errors" />

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <x-label for="email" :value="__('Email')" />
                <x-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <div class="mb-3">
                <x-label for="password" :value="__('Password')" />
                <x-input id="password" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="mb-3 form-check">
                <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                <label class="form-check-label" for="remember_me">{{ __('Remember me') }}</label>
            </div>

            <div class="d-flex align-items-center justify-content-between">
                @if (Route::has('password.request'))
                    <a class="small text-muted" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @else
                    <span></span>
                @endif

                <x-button>
                    {{ __('Log in') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
