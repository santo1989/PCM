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
            @if (session('message'))
                <div class="alert alert-success">
                    <span class="close" data-dismiss="alert">&times;</span>
                    <strong>{{ session('message') }}.</strong>
                </div>
            @endif

            <x-backend.layouts.elements.errors />

            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">

                            <x-backend.form.anchor :href="route('handCashes.create')" type="create" />
                            <x-backend.form.anchor :href="route('handCashes_transfer_create')" type="Transfer" />

                        </div>
                        <!-- /.card-header -->
                        <div class="card-body justify-content-between">
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
                                            <h4>Loan To Other</h4>
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
                                             <h4>My Loan</h4>
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
                                            <h4>Bank Cash Handlings</h4>
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
                                           <h4>Hand Cash</h4>
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Cash</th>
                                                        <td>Balence</td>

                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <th>Shanta Hand Cash Balence</th>
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
                        <div class="card-body">
                            {{-- handCash Table goes here --}}

                            <div class="row justify-content-center  text-center">
                                <div class="col-md-12">

                                    <table id="datatablesSimple" class="table table-bordered table-hover">
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
                                            @php $sl=0 @endphp
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

                                                        <form style="display:inline"
                                                            action="{{ route('handCashes.destroy', ['handCash' => $handCash->id]) }}"
                                                            method="POST">
                                                            @csrf
                                                            @method('delete')

                                                            <button
                                                                onclick="return confirm('Are you sure want to delete ?')"
                                                                class="btn btn-outline-danger my-1 mx-1 inline btn-sm"
                                                                type="submit"><i class="bi bi-trash"></i>
                                                                Delete</button>
                                                        </form>

                                                    </td>
                                                </tr>
                                            @endforeach

                                        </tbody>
                                    </table>
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

    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
