<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/" class="text-decoration-none">
                <x-application-logo light />
            </a>
        </x-slot>

        <div class="mb-3 small text-muted">
            {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
        </div>

        <x-auth-validation-errors class="mb-3" :errors="$errors" />

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div class="mb-3">
                <x-label for="password" :value="__('Password')" />
                <x-input id="password" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="d-flex justify-content-end">
                <x-button>
                    {{ __('Confirm') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
