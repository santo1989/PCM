{{-- One column of the income/expense sheet: totals per row on top, then per period. $rows: [['name', 'months' => [period => amount]]], periods = months or years. --}}
@php
    $fmt = fn($v) => abs($v) < 0.005 ? '–' : number_format($v, 2);
    $withTotals = collect($rows)->map(fn($r) => $r + ['total' => array_sum($r['months'])])->sortByDesc(fn($r) => abs($r['total']))->values();
    $grand = $withTotals->sum('total');
@endphp
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}-title">{{ $title }} — {{ $yearLabel }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Total per {{ $rowLabel }}</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="table-light"><tr><th>{{ ucfirst($rowLabel) }}</th>@isset($extra)<th class="text-end">In</th><th class="text-end">Out</th>@endisset<th class="text-end">Total</th><th class="text-end">Share</th></tr></thead>
                    <tbody>
                        @foreach ($withTotals as $r)
                            <tr>
                                <td>{{ $r['name'] }}</td>
                                @isset($extra)<td class="text-end">{{ $fmt(array_sum($r['in'])) }}</td><td class="text-end">{{ $fmt(array_sum($r['out'])) }}</td>@endisset
                                <td class="text-end fw-semibold {{ $r['total'] < 0 ? 'text-danger' : '' }}">{{ $fmt($r['total']) }}</td>
                                <td class="text-end text-muted">{{ $grand != 0 ? round(100 * $r['total'] / $grand, 1) . '%' : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="fw-bold"><td>Total</td>@isset($extra)<td></td><td></td>@endisset<td class="text-end">{{ $fmt($grand) }}</td><td></td></tr></tfoot>
                </table>

                <h6>{{ $year === 'all' ? 'Year by year' : 'Month by month' }}</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover small text-nowrap">
                        <thead class="table-light">
                            <tr><th>{{ ucfirst($rowLabel) }}</th>@foreach ($periods as $label)<th class="text-end">{{ $label }}</th>@endforeach<th class="text-end">Total</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($withTotals as $r)
                                <tr>
                                    <td>{{ $r['name'] }}</td>
                                    @foreach ($r['months'] as $v)<td class="text-end {{ $v < 0 ? 'text-danger' : '' }}">{{ $fmt($v) }}</td>@endforeach
                                    <td class="text-end fw-semibold">{{ $fmt($r['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total</td>
                                @foreach (array_keys($periods) as $p)<td class="text-end">{{ $fmt($withTotals->sum(fn($r) => $r['months'][$p])) }}</td>@endforeach
                                <td class="text-end">{{ $fmt($grand) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @isset($extra)<p class="small text-muted mb-0">{{ $extra }}</p>@endisset
            </div>
        </div>
    </div>
</div>
