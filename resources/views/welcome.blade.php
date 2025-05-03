    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
        <!-- Google Fonts -->
        <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet" />
        <!-- MDB -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.1/mdb.min.css" rel="stylesheet" />

    </head>

    <body>
        <!-- The video -->
        {{-- style="background-image: linear-gradient(#40c47c,#40c47c,#40c47c); background-size: cover; background-repeat: repeat; height:100vh;" --}}
        <div class="container-fluid">


            <div style="margin: 0 auto; position: fixed; background: rgba(0, 0, 0, 0.5); color: #f1f1f1; left:33vw; right:33vw; top:20vh; width: 33vw;"
                id="glassPanel">
                <div class="p-5 text-white text-center" id="logo">
                    <div class="text-center py-5">
                        <img src="{{ asset('images/assets/logo.png') }}" alt="" heigt=600vh; width=200vw;
                            class="img rounded text-center text-white">
                    </div>

                    <div class="flex justify-content-center text-center py-1">
                        @if (Route::has('login'))
                            <div class="hidden fixed top-0 right-0 px-6 py-4 sm:block">
                                @auth
                                    <a href="{{ url('home') }}" class="btn btn-outline-light btn-lg">Dashboard</a>
                                @else
                                    {{-- <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg">Log in</a> --}}
                                    <a type="button" class="btn btn-outline-light btn-lg" data-bs-toggle="modal"
                                        data-bs-target="#loginModal" id="loginPanel">
                                        Log in
                                    </a>


                                    {{-- @if (Route::has('register'))
                                        <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg">Create New Account</a>
                                       
                                    @endif --}}
                                @endauth
                            </div>
                        @endif
                    </div>

                </div>
            </div>


        </div>
        <div class="footer fixed-bottom mt-5 p-4 text-white text-center text-light pt-1" id="footer">
            {{ now()->year }} -Santo
        </div>

        <!-- login Modal start-->
        <div class="modal fade text-light" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel"
            data-bs-backdrop="static" aria-hidden="true">
            <div class="modal-dialog modal-lg text-light"
                style="margin: 0 auto; position: fixed; left:33%; right:33%; top:10%; width: 33%; opacity: 1; background-color: transparent; background-color: rgba(0,0,0,.5)">
                <div class="modal-content" style="background-color: transparent; border: 2px solid #40c47c;">
                    <div class="modal-header text-light">
                        <h5 class="modal-title text-center text-light" id="loginModalLabel">Log in</h5>

                        <button type="button" class="btn btn-light btn-close" data-bs-dismiss="modal"
                            aria-label="Close" style="background-color: white; border-color: white; color: black;"
                            onmouseover="this.classList.add('btn-danger')"
                            onmouseout="this.classList.remove('btn-danger')"></button>

                    </div>
                    <div class="modal-body text-center text-light" style="background-color: transparent;">
                        <!-- Your x-guest-layout code here -->
                        <div class="card p-3 m-3" style="background-color: transparent;">
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
