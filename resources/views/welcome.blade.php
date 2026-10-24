    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Personal Cost Management') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Bootstrap Icons (replaces Font Awesome + MDB here, matches the rest of the app) -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        <link href="{{ asset('ui/backend/css/app-theme.css') }}" rel="stylesheet">

        <style>
            body.welcome-gradient-bg {
                background: var(--grad-primary);
                min-height: 100vh;
            }
        </style>
    </head>

    <body class="welcome-gradient-bg">
        <div class="min-vh-100 d-flex flex-column">
            <div class="flex-grow-1 d-flex align-items-center justify-content-center px-3 py-5">
                <div class="p-4 p-md-5 text-white text-center" style="background: rgba(0, 0, 0, 0.35); border-radius: 1rem; max-width: 480px; width: 100%;">
                    <img src="{{ asset('images/assets/logo.png') }}" alt="" class="img rounded mb-4" style="max-height: 160px;">

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('home') }}" class="btn btn-outline-light btn-lg">Dashboard</a>
                        @else
                            <a type="button" class="btn btn-outline-light btn-lg" data-bs-toggle="modal"
                                data-bs-target="#loginModal" id="loginPanel">
                                Log in
                            </a>
                        @endauth
                    @endif
                </div>
            </div>

            <div class="p-3 text-white text-center small" style="background: rgba(0, 0, 0, 0.2);">
                {{ now()->year }} -Santo
            </div>
        </div>

        <!-- login Modal start-->
        <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel"
            data-bs-backdrop="static" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content text-light" style="background: rgba(20, 20, 30, 0.85); border: 1px solid rgba(255,255,255,0.25);">
                    <div class="modal-header text-light border-0">
                        <h5 class="modal-title text-center text-light" id="loginModalLabel">Log in</h5>

                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>

                    </div>
                    <div class="modal-body text-center text-light">
                        <!-- Your x-guest-layout code here -->
                        <div class="card p-3 m-3" style="background-color: transparent; border: none;">
                            <x-slot name="logo">
                                <a href="/">
                                    <img src="{{ asset('images/assets/logo.png') }}" alt="" heigt=600px;
                                        width=200px; class="img rounded text-center text-white" />
                                </a>
                            </x-slot>

                            <!-- Session Status -->
                            <x-auth-session-status class="mb-4" :status="session('status')" />

                            <!-- Validation Errors -->
                            <x-auth-validation-errors class="mb-4" :errors="$errors" />

                            <form method="POST" action="{{ route('login') }}">
                                @csrf

                                <!-- Email Address -->
                                <div class="mb-3 text-light">
                                    <label for="exampleFormControlInput1" class="form-label text-light">Email
                                        address</label>
                                    <input type="email" class="form-control" id="exampleFormControlInput1"
                                        placeholder="name@ntg.com.bd" name="email">
                                </div>

                                <!-- Password -->
                                <div class="mt-4 text-light">
                                    <label for="inputPassword5" class="form-label text-light">Password</label>
                                    <input type="password" id="inputPassword5" class="form-control"
                                        aria-describedby="passwordHelpBlock" name="password">
                                </div>

                                <!-- Remember Me -->
                                <div class="block mt-4 text-light">
                                    <label for="remember_me" class="inline-flex items-center">
                                        <input id="remember_me" type="checkbox"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            name="remember">
                                        <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                                    </label>
                                </div>

                                <div class="flex items-center justify-end mt-4 text-light">
                                    @if (Route::has('password.request'))
                                        <a class="underline text-sm text-gray-600 hover:text-gray-900"
                                            href="{{ route('password.request') }}">
                                            {{ __('Forgot your password?') }}
                                        </a>
                                    @endif

                                    <button type="submit" class="btn btn-outline-light btn-lg">Log in</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>




        <!-- login Modal end-->
    </body>


    </html>
