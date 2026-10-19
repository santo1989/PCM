<x-backend.layouts.master>
    <x-slot name="pageTitle">Recurring Transactions</x-slot>

    <x-backend.layouts.elements.message :message="session('message')" />
    <x-backend.layouts.elements.errors />

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">Recurring transactions</h2>
            <div class="text-muted small">Monthly income and account-to-account transfers (DPS, savings…), added automatically.</div>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('recurring.run_now') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-play-circle"></i> Run due now
                </button>
            </form>
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#newRecurring"
                aria-expanded="{{ $errors->any() || $recurring->isEmpty() ? 'true' : 'false' }}" aria-controls="newRecurring">
                <i class="bi bi-plus-lg"></i> New
            </button>
        </div>
    </div>

    <div class="collapse {{ $errors->any() || $recurring->isEmpty() ? 'show' : '' }} mb-4" id="newRecurring">
        <div class="card">
            <div class="card-header">New recurring transaction</div>
            <div class="card-body">
                <form method="POST" action="{{ route('recurring.store') }}">
                    @csrf
                    @include('backend.library.recurring._form', ['recurring' => null])
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card" id="live-recurring" data-live-region>
        <div class="card-body">
            @if ($recurring->isEmpty())
                <div class="text-center text-muted py-4">
                    <i class="bi bi-arrow-repeat fs-1 d-block mb-2"></i>
                    No recurring transactions yet.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-responsive-cards">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Category / Accounts</th>
                                <th class="text-end">Amount</th>
                                <th>Schedule</th>
                                <th>Next</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recurring as $rule)
                                @php($isIncome = !$rule->isTransfer() && optional($rule->category)->types === 'INCOME')
                                <tr class="{{ $rule->is_active ? '' : 'opacity-75' }}">
                                    <td data-label="Name" class="fw-semibold">{{ $rule->name }}</td>
                                    <td data-label="Type">
                                        <span class="badge {{ $rule->isTransfer() ? 'text-bg-info' : 'text-bg-success' }}">
                                            {{ $rule->isTransfer() ? 'Transfer' : 'Income' }}
                                        </span>
                                    </td>
                                    <td data-label="Category / Accounts">{{ $rule->targetLabel() }}</td>
                                    <td data-label="Amount" class="text-end fw-semibold {{ $rule->isTransfer() ? '' : ($isIncome ? 'text-success' : 'text-danger') }}">
                                        {{ $rule->isTransfer() ? '' : ($isIncome ? '+' : '−') }}{{ number_format($rule->amount, 2) }}
                                    </td>
                                    <td data-label="Schedule" class="small">
                                        {{ $rule->scheduleLabel() }}
                                        @if ($rule->end_date)
                                            <div class="text-muted">until {{ $rule->end_date->format('d M Y') }}</div>
                                        @endif
                                    </td>
                                    <td data-label="Next" class="small text-nowrap">
                                        {{ $rule->is_active && $rule->next_run_on ? $rule->next_run_on->format('d M Y') : '—' }}
                                    </td>
                                    <td data-label="Status">
                                        <span class="badge {{ $rule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $rule->is_active ? 'Active' : 'Paused' }}
                                        </span>
                                    </td>
                                    <td data-label="Actions" class="text-end text-nowrap">
                                        <a href="{{ route('recurring.edit', $rule) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span>
                                        </a>
                                        <form method="POST" action="{{ route('recurring.toggle', $rule) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                title="{{ $rule->is_active ? 'Pause' : 'Resume' }}">
                                                <i class="bi {{ $rule->is_active ? 'bi-pause' : 'bi-play' }}"></i>
                                                <span class="visually-hidden">{{ $rule->is_active ? 'Pause' : 'Resume' }}</span>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                            onclick="confirmRecurringDelete('{{ route('recurring.destroy', $rule) }}')">
                                            <i class="bi bi-trash"></i><span class="visually-hidden">Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <script>
        function confirmRecurringDelete(url) {
            Swal.fire({
                title: 'Delete this recurring transaction?',
                text: 'Entries it already created are kept.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete'
            }).then(function (result) {
                if (!result.isConfirmed) return;
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.innerHTML = '@csrf @method('delete')';
                document.body.appendChild(form);
                form.submit();
            });
        }
    </script>
</x-backend.layouts.master>
