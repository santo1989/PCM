<x-guest-layout>
    <div class="w-100" style="max-width: 560px;">
        <div class="card overflow-hidden">
            <div class="p-4 text-center" style="background: var(--grad-primary);">
                <a href="/" class="text-decoration-none">
                    <x-application-logo light />
                </a>
            </div>
            <div class="card-body p-4">
                <h4 class="mb-4">Create an Account</h4>

                <x-auth-validation-errors class="mb-3" :errors="$errors" />

                <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-label for="name" :value="__('Name')" />
                            <x-input id="name" type="text" name="name" :value="old('name')" required autofocus />
                        </div>
                        <div class="col-md-6 mb-3">
                            <x-label for="email" :value="__('Email')" />
                            <x-input id="email" type="email" name="email" :value="old('email')" required />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-label for="mobile" :value="__('Mobile')" />
                            <x-input id="mobile" type="text" name="mobile" :value="old('mobile')" required />
                        </div>
                        <div class="col-md-6 mb-3">
                            <x-label for="dob" :value="__('Date of Birth')" />
                            <x-input id="dob" type="date" name="dob" :value="old('dob')" required />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-label for="password" :value="__('Password')" />
                            <x-input id="password" type="password" name="password" required autocomplete="new-password" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <x-label for="password_confirmation" :value="__('Confirm Password')" />
                            <x-input id="password_confirmation" type="password" name="password_confirmation" required />
                        </div>
                    </div>

                    <div class="mb-3">
                        <x-label for="picture" :value="__('Picture (optional)')" />
                        <x-input id="picture" type="file" name="picture" accept="image/*" onchange="previewPicture(event)" />
                        <img id="picturePreview" class="img-thumbnail mt-2" height="100" width="100" hidden>
                    </div>

                    <div class="d-flex justify-content-end">
                        <x-button>
                            {{ __('Register') }}
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewPicture(event) {
            var preview = document.getElementById('picturePreview');
            if (event.target.files && event.target.files[0]) {
                preview.src = URL.createObjectURL(event.target.files[0]);
                preview.hidden = false;
            }
        }
    </script>
</x-guest-layout>
