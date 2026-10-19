{{-- Next few scheduled recurring entries. --}}
@php
    try {
        $upcoming = \App\Models\RecurringTransaction::with('category:id,name,types')
            ->where('is_active', true)->whereNotNull('next_run_on')
            ->orderBy('next_run_on')->limit(5)->get();
    } catch (\Illuminate\Database\QueryException $e) {
        $upcoming = collect(); // table not migrated yet
    }
@endphp
<div {{ $attributes->class(['card']) }}>
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-arrow-repeat me-1"></i> Upcoming recurring</span>
        <a href="{{ route('recurring.index') }}" class="small fw-normal">Manage</a>
    </div>
    <div class="list-group list-group-flush">
        @forelse ($upcoming as $rule)
            @php($isIncome = !$rule->isTransfer() && optional($rule->category)->types === 'INCOME')
            <div class="list-group-item d-flex align-items-center gap-3 bg-transparent">
                <div class="text-center lh-1" style="min-width: 42px;">
                    <div class="small text-muted text-uppercase">{{ $rule->next_run_on->format('M') }}</div>
                    <div class="fw-bold fs-5">{{ $rule->next_run_on->format('d') }}</div>
                </div>
                <div class="flex-grow-1 text-truncate">
                    <div class="fw-semibold text-truncate">{{ $rule->name }}</div>
                    <div class="small text-muted text-truncate">{{ $rule->targetLabel() }}</div>
                </div>
                <div class="fw-semibold text-nowrap {{ $rule->isTransfer() ? '' : ($isIncome ? 'text-success' : 'text-danger') }}">
                    {{ $rule->isTransfer() ? '' : ($isIncome ? '+' : '−') }}{{ number_format($rule->amount, 0) }}
                </div>
            </div>
        @empty
            <div class="list-group-item text-center text-muted small py-4 bg-transparent">
                Nothing scheduled.
                <div class="mt-2"><a href="{{ route('recurring.index') }}" class="btn btn-sm btn-outline-primary">Add a recurring entry</a></div>
            </div>
        @endforelse
    </div>
</div>
