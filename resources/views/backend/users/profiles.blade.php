<x-backend.layouts.master>
    <x-slot name="pageTitle">
        {{ $user->name }}
    </x-slot>

    <div class="container-fluid">
        <div class="row gutters-sm">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-column align-items-center text-center">
                            <img src="{{ asset('images/users/' . $user->picture) }}" class="rounded-circle"
                                width="150" alt="{{ $user->name }}">
                            <div class="mt-3">
                                <h4>{{ $user->name ?? 'No Data found' }}</h4>
                                <p class="text-muted font-size-sm">{{ $user->email }}</p>
                            </div>

                            <div class="mt-3">
                                <x-backend.form.anchor :href="route('users.edit', ['user' => $user->id])" type="edit" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mt-3">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                            <h6 class="mb-0">Role</h6>
                            <span class="text-secondary">{{ $user->role->name ?? 'No Data found' }}</span>
                        </li>

                        <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                            <h6 class="mb-0">Mobile</h6>
                            <span class="text-secondary">{{ $user->mobile ?? 'No Data found' }}</span>
                        </li>

                        @if ($user->mobile)
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="mb-0">WhatsApp</h6>
                                <span class="text-secondary"><a href="https://wa.me/88{{ $user->mobile }}"
                                        target="_blank" rel="noopener"><img
                                            src="{{ asset('images/assets/whatsapp.png') }}" alt="whatsapp"
                                            width="30px"></a></span>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0">Date of Birth</h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                {{ $user->dob ?? 'No Data found' }}
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0">Status</h6>
                            </div>
                            <div class="col-sm-9">
                                @if ($user->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-sm-3">
                                <h6 class="mb-0">Last Seen</h6>
                            </div>
                            <div class="col-sm-9 text-secondary">
                                {{ $user->last_seen ? \Carbon\Carbon::parse($user->last_seen)->diffForHumans() : 'No Data found' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-backend.layouts.master>
