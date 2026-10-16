{{-- Shown once after saving an expense that pushes a category near/over its monthly budget. --}}
@if ($warnings = session('budget_warnings'))
    <div class="alert alert-warning alert-dismissible d-flex gap-2 no-print" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">Budget alert</div>
            @foreach ($warnings as $row)
                <div class="small">
                    {{ $row['name'] }}: {{ number_format($row['spent'], 2) }} of {{ number_format($row['budget'], 2) }}
                    ({{ $row['percent'] }}%{{ $row['percent'] >= 100 ? ' — over budget' : '' }})
                </div>
            @endforeach
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
