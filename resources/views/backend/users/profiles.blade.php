<x-backend.layouts.master>

    <div class="container">
        <div class="main-body">
            <div class="row gutters-sm">
                {{-- 1st card start --}}
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center text-center">
                                <img src="{{ asset('images/users/' . $user->picture) }}" class="rounded-circle"
                                    width="150" alt="{{ $user->name }}">
                                <div class="mt-3">
                                    <h4>{{ $user->name ?? 'No Data found' }}</h4>
                                    <p class="text-muted font-size-sm">{{ $user->email }}</p>

                                    {{-- <p class="text-muted font-size-sm">
                                        Service Length: {{ \Carbon\Carbon::parse($user->joining_date)->diffForHumans() ?? 'No Data found' }}	
                                    </p> --}}
                                </div>

                                <div class="mt-3">
                                    <x-backend.form.anchor :href="route('users.edit', ['user' => $user->id])" type="edit" />
                                    {{-- <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary btn-sm"
                                        style="background-image: linear-gradient(#40c47c,#40c47c,#40c47c);display:inline;">Edit</a> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mt-3">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="mb-0">Employee ID</h6>
                                <span class="text-secondary">{{ $user->emp_id ?? 'No Data found' }}</span>
                            </li>

                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="mb-0">Mobile</h6>
                                <span class="text-secondary">{{ $user->mobile ?? 'No Data found' }}</span>

                            </li>

                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="mb-0"> WhatsApp</h6>
                                <span class="text-secondary"><a href="https://wa.me/88{{ $user->mobile }}"
                                        target="blank"><img src="{{ asset('images/assets/whatsapp.png') }}"
                                            alt="whatsapp" width="30px"></a></span>
                            </li>
                        </ul>
                    </div>
                </div>
                {{-- 1st card end  --}}
                {{-- 2nd card start  --}}
                <div class="col-md-8">
                    {{-- card  1 start --}}
                    <div class="card mb-3">
                        <div class="card-body">
                                <div class="row">
                                <div class="col-sm-3">
                                    <h6 class="mb-0">Joining Date</h6>
                                </div>
                                <div class="col-sm-9 text-secondary">
                                    {{ $user->joining_date ?? 'No Data found' }}
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3">
                                    <h6 class="mb-0">Division</h6>
                                </div>
                                <div class="col-sm-9 text-secondary">
                                    {{ $user->division->name ?? 'No Data found' }}
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3">
                                    <h6 class="mb-0">Company</h6>
                                </div>
                                <div class="col-sm-9 text-secondary">
                                    {{ $user->company->name ?? 'No Data found' }}
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-sm-3">
                                    <h6 class="mb-0">Department</h6>
                                </div>
                                <div class="col-sm-9 text-secondary">
                                    {{ $user->department->name ?? 'No Data found' }}
                                </div>
                            </div>
                            <hr>

                            <div class="row">
                                <div class="col-sm-3">

                                    <h6 class="mb-0">Designation:</h6>
                                </div>
                                <div class="col-sm-9 text-secondary">

                                    {{ $user->designation->name ?? 'No Data found' }}
                                </div>
                            </div>

                        </div>
                    </div>
                    {{-- card  1 end --}}

                </div>
                {{-- 2nd card end  --}}
            </div>
            {{-- row gutters-sm end --}}
        </div>
        {{-- main-body end --}}
    </div>

    <style>
        body {
            margin-top: 0px;
            color: #1a202c;
            text-align: left;
            background-color: #e2e8f0;
        }

        .main-body {
            padding: 15px;
        }

        .card {
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, .1), 0 1px 2px 0 rgba(0, 0, 0, .06);
        }

        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 0 solid rgba(0, 0, 0, .125);
            border-radius: .25rem;
        }

        .card-body {
            flex: 1 1 auto;
            min-height: 1px;
            padding: 1rem;
        }

        .gutters-sm {
            margin-right: -8px;
            margin-left: -8px;
        }

        .gutters-sm>.col,
        .gutters-sm>[class*=col-] {
            padding-right: 8px;
            padding-left: 8px;
        }

        .mb-3,
        .my-3 {
            margin-bottom: 1rem !important;
        }

        .bg-gray-300 {
            background-color: #e2e8f0;
        }

        .h-100 {
            height: 100% !important;
        }

        .shadow-none {
            box-shadow: none !important;
        }
    </style>

</x-backend.layouts.master>
