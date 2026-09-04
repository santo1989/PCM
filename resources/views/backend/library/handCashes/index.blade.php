<x-backend.layouts.master>
    <x-slot name="pageTitle">
        HandCash List
    </x-slot>

    {{-- <x-slot name='breadCrumb'>
        <x-backend.layouts.elements.breadcrumb>
            <x-slot name="pageHeader"> HandCash </x-slot>

            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('handCashes.index') }}">HandCash</a></li>
        </x-backend.layouts.elements.breadcrumb>
    </x-slot> --}}

    <section class="content">
        <div class="container-fluid">
            <x-backend.layouts.elements.message :message="session('message')" />
            <x-backend.layouts.elements.errors />

            <x-backend.insights-panel :insights="\App\Services\InsightEngine::dashboardSummary()" />

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">

                            <x-backend.form.anchor :href="route('handCashes.create')" type="create" />
                            <x-backend.form.anchor :href="route('handCashes_transfer_create')" type="Transfer" />

                        </div>
                        <!-- /.card-header -->
                        <div class="card-body justify-content-between">
                            {{-- Date Filter Form --}}
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <form action="{{ route('handCashes.index') }}" method="GET"
                                        class="form-inline justify-content-center">
                                        <div class="form-group mr-3">
                                            <label for="balance_date_start" class="mr-2">From:</label>
                                            <input type="date" name="balance_date_start" id="balance_date_start"
                                                class="form-control" value="{{ request('balance_date_start') }}">
                                        </div>
                                        <div class="form-group mr-3">
                                            <label for="balance_date_end" class="mr-2">To:</label>
                                            <input type="date" name="balance_date_end" id="balance_date_end"
                                                class="form-control" value="{{ request('balance_date_end') }}">
                                        </div>
                                        <button type="submit" class="btn btn-primary mr-2">
                                            <i class="bi bi-filter"></i> Filter
                                        </button>
                                        <a href="{{ route('handCashes.index') }}" class="btn btn-secondary">
                                            <i class="bi bi-x-circle"></i> Clear
                                        </a>
                                    </form>
                                </div>
                            </div>

                            {{-- handCash Table goes here --}}

                            <div class="row justify-content-between text-center">
                                <div class="col-md-12">
                                    <div class="row justify-content-center text-center">
                                        <div class="col-md-3">
                                            <h4>Mobile Cash</h4>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Account</th>
                                                        <th>Balence</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($mobile_cash as $mobile)
                                                        <tr>
                                                            <td>{{ str_replace('_', ' ', $mobile->rules) }}</td>
                                                            <td>{{ $mobile->Balance }}</td>
                                                        </tr>
                                                    @endforeach
                                                    <tr>
                                                        <td>Total</td>
                                                        <td>{{ $handCashes_Mobile_balence }}</td>
                                                    </tr>


                                                </tbody>
                                            </table>
                                            <h4>Credit Card</h4>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Credit pay</th>
                                                        <th>Borrow</th>
                                                    </tr>
                                                </thead>
                                                <tbody>

                                                    <tr>
                                                        <td>{{ $CreditCard_Credit }}</td>
                                                        <td>{{ $CreditCard_withdraw }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Card Balance</td>
                                                        <td>{{ $CreditCard_balance }}</td>
                                                    </tr>


                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="col-md-3">
                                            <h4>Loan To Other ( Receivable by Me )</h4>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Balence</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>{{ $handCashes_loan_balence }}</td>
                                                    </tr>


                                                </tbody>
                                            </table>
                                            <h4>My Loan ( Need to Paid by Me )</h4>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Pay</th>
                                                        <th>Borrow</th>
                                                        <th>Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>

                                                    <tr>
                                                        <td>My Loan</td>
                                                        <td>{{ $MyLoan_pay }}</td>
                                                        <td>{{ $MyLoan_borrow }}</td>
                                                        <td>{{ $MyLoan_balance }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td>DPS Loan</td>
                                                        <td>{{ $DPSLoan_pay }}</td>
                                                        <td>{{ $DPSLoan_borrow }}</td>
                                                        <td>{{ $DPSLoan_balance }}</td>
                                                    </tr>


                                                </tbody>
                                            </table>


                                        </div>
                                        <div class="col-md-3">
                                            <h6>Bank Savings / Investment in Dhaka Stock Exchanges / DPS / FD Cash Details</h6>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Account</th>
                                                        <th>Balence</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($bank_cash as $mobile)
                                                        <tr>
                                                            <td>{{ str_replace('_', ' ', $mobile->rules) }}</td>
                                                            <td>{{ $mobile->Balance }}</td>
                                                        </tr>
                                                    @endforeach
                                                    <tr>
                                                        <td>Total</td>
                                                        <td>{{ $handCashes_Bank_balence }}</td>
                                                    </tr>


                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="col-md-3">
                                            <h4>Cash in Hand</h4>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Cash</th>
                                                        <td>Balence</td>

                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <th>Locker Cash Balence</th>
                                                        <td>{{ $handCashes_Cash_balence }}</td>

                                                    </tr>
                                                    <tr>
                                                        <th>Peti Cash Balance</th>
                                                        <td>{{ $handCashes_Peti_balence }}</td>
                                                    </tr>


                                                </tbody>
                                            </table>
                                            <h4 class="pt-2">Total Balence</h4>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Balence</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td>Net Cash</td>
                                                        <td>{{ $hands }}</td>
                                                    </tr>
                                                    <tr>
                                                        <td>Gross Cash</td>
                                                        <td>{{ $total }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                </div>




                            </div>
                        </div>
                        <div class="card-header text-center">

                            <h4>All Cash Handlings</h4>
                        </div>
                        <!-- /.card-header -->
                        {{-- handCash Table filter here --}}
                        <div class="card-header text-center">
                            <form action="{{ route('handCashes.index') }}" method="GET"
                                class="form-inline justify-content-center">
                                <div class="form-group mr-3">
                                    <label for="handCashes_date_start" class="mr-2">From:</label>
                                    <input type="date" name="handCashes_date_start" id="handCashes_date_start"
                                        class="form-control" value="{{ request('handCashes_date_start') }}">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="handCashes_date_end" class="mr-2">To:</label>
                                    <input type="date" name="handCashes_date_end" id="handCashes_date_end"
                                        class="form-control" value="{{ request('handCashes_date_end') }}">
                                </div>
                                <!--Name, Types, Rules filter-->
                                <div class="form-group mr-3">
                                    <label for="handCashes_name" class="mr-2">Name:</label>
                                    <input type="text" name="handCashes_name" id="handCashes_name"
                                        class="form-control" value="{{ request('handCashes_name') }}">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="handCashes_types[]">HandCash Types</label>
                                    @php
                                        $selectedHandCashTypes = array_map(
                                            'strtoupper',
                                            (array) request('handCashes_type', request('types', [])),
                                        );
                                    @endphp
                                    <select class="form-control" name="handCashes_type[]" id="handCashes_type" multiple>
                                        <option value="SAVE"
                                            {{ in_array('SAVE', $selectedHandCashTypes) ? 'selected' : '' }}>
                                            Savings</option>
                                        <option value="WIDROWS"
                                            {{ in_array('WIDROWS', $selectedHandCashTypes) ? 'selected' : '' }}>
                                            Withdraws</option>
                                    </select>
                                </div>
                                <div class="form-group mr-3">
                                    <label for="handCashes_rule">Cash Rules</label>
                                    @php
                                        $selectedHandCashRules = (array) request(
                                            'handCashes_rule',
                                            request('rules', []),
                                        );
                                    @endphp
                                    <select class="form-control" name="handCashes_rule[]" id="handCashes_rule" multiple>
                                        @foreach (config('finance.handcash_rules') as $ruleKey => $ruleLabel)
                                            <option value="{{ $ruleKey }}"
                                                {{ in_array($ruleKey, array_map('strtoupper', $selectedHandCashRules)) ? 'selected' : '' }}>
                                                {{ $ruleLabel }}</option>
                                        @endforeach
                                    </select>

                                </div>
                                <button type="submit" class="btn btn-primary mr-2">
                                    <i class="bi bi-filter"></i> Filter
                                </button>
                                <a href="{{ route('handCashes.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            </form>

                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">


                            <div class="row justify-content-center  text-center">
                                <div class="col-md-12">

                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sl#</th>
                                                <th>Date</th>
                                                <th>Name</th>
                                                <th>Types</th>
                                                <th>Rules</th>
                                                <th>Amount</th>
                                                <th>Actions</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $sl = ($handCashes->currentPage() - 1) * $handCashes->perPage(); @endphp
                                            @foreach ($handCashes as $handCash)
                                                <tr>
                                                    <td>{{ ++$sl }}</td>
                                                    <td>{{ $handCash->date ? \Carbon\Carbon::parse($handCash->date)->format('d-M-Y') : '' }}
                                                    </td>
                                                    <td>{{ $handCash->name }}</td>
                                                    <td>{{ $handCash->types }}</td>
                                                    <td>{{ str_replace('_', ' ', $handCash->rules) }}</td>
                                                    <td>{{ $handCash->amount }}</td>
                                                    <td>

                                                        <x-backend.form.anchor :href="route('handCashes.edit', [
                                                            'handCash' => $handCash->id,
                                                        ])" type="edit" />



                                                        <x-backend.form.anchor :href="route('handCashes.show', [
                                                            'handCash' => $handCash->id,
                                                        ])" type="show" />

                                                        <button type="button"
                                                            onclick="confirmDelete('{{ route('handCashes.destroy', ['handCash' => $handCash->id]) }}')"
                                                            class="btn btn-outline-danger my-1 mx-1 inline btn-sm">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>

                                                    </td>
                                                </tr>
                                            @endforeach

                                        </tbody>
                                    </table>
                                    <div class="d-flex justify-content-center mt-3">
                                        {{ $handCashes->links() }}
                                    </div>
                                </div>

                            </div>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->


                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>

    <script>
        function confirmDelete(url) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form if the user confirms
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    form.innerHTML = `@csrf @method('delete')`;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>

</x-backend.layouts.master>
