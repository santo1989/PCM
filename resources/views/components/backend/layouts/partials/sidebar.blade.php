@php
    $isActive = fn(array $item) => request()->routeIs($item['match'] ?? $item['route']);

    // Yearly Summary: one grouped query (was 2 queries per year + 2 per category per year).
    $yearRows = \App\Models\ExpenseCalculation::query()
        ->whereIn('types', ['INCOME', 'EXPENSE'])
        ->whereNotNull('date')
        ->groupBy(\DB::raw('YEAR(date)'), 'types', 'category_id')
        ->select(\DB::raw('YEAR(date) as y'), 'types', 'category_id', \DB::raw('SUM(amount) as total'))
        ->get();
    $categoryNames = \App\Models\Category::pluck('name', 'id');

    $years = [];
    foreach ($yearRows as $row) {
        $type = strtolower($row->types);
        $name = $categoryNames[$row->category_id] ?? 'Uncategorized';
        $years[$row->y][$type . 'Total'] = ($years[$row->y][$type . 'Total'] ?? 0) + $row->total;
        $years[$row->y][$type][$name] = ($years[$row->y][$type][$name] ?? 0) + $row->total;
    }
    krsort($years);
@endphp

<aside class="offcanvas-lg offcanvas-start app-sidebar no-print" tabindex="-1" id="appSidebar" aria-label="Main menu">
    <div class="offcanvas-header border-bottom d-lg-none">
        <a class="app-brand" href="{{ route('home') }}">
            <span class="app-brand-mark"><i class="bi bi-wallet2"></i></span> Personal Cost
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar"
            aria-label="Close menu"></button>
    </div>

    <div class="offcanvas-body">
        <nav class="nav flex-column">
            @foreach (config('navigation.groups') as $group)
                @if (empty($group['can']) || Gate::allows($group['can']))
                    <div class="app-nav-heading">{{ $group['label'] }}</div>
                    @foreach ($group['items'] as $item)
                        <a class="app-nav-link {{ $isActive($item) ? 'active' : '' }}" href="{{ route($item['route']) }}"
                            @if ($isActive($item)) aria-current="page" @endif>
                            <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>

        @if ($years)
            <div class="app-nav-heading">Yearly Summary</div>
            <table class="table table-sm table-borderless small mb-2">
                <thead>
                    <tr class="text-muted">
                        <th class="ps-2 fw-normal">Year</th>
                        <th class="text-end fw-normal">Income</th>
                        <th class="text-end pe-2 fw-normal">Expense</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($years as $year => $data)
                        <tr>
                            <td class="ps-2">{{ $year }}</td>
                            @foreach (['income' => 'success', 'expense' => 'danger'] as $type => $color)
                                <td class="text-end {{ $type === 'expense' ? 'pe-2' : '' }}">
                                    @if (($data[$type . 'Total'] ?? 0) > 0)
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-{{ $color }}"
                                            data-bs-toggle="modal" data-bs-target="#{{ $type }}DetailsModal{{ $year }}">
                                            {{ number_format($data[$type . 'Total'], 0) }}
                                        </button>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="app-sidebar-footer">
            Signed in as <span class="fw-semibold">{{ auth()->user()->role->name ?? 'User' }}</span>
        </div>
    </div>
</aside>

{{-- Year detail modals live outside the offcanvas so they stack above it correctly --}}
@foreach ($years as $year => $data)
    @foreach (['income' => ['Income', 'text-success'], 'expense' => ['Expense', 'text-danger']] as $type => [$title, $color])
        @continue(empty($data[$type]))
        @php(arsort($data[$type]))
        <div class="modal fade" id="{{ $type }}DetailsModal{{ $year }}" tabindex="-1"
            aria-labelledby="{{ $type }}DetailsModalLabel{{ $year }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="{{ $type }}DetailsModalLabel{{ $year }}">{{ $title }} details · {{ $year }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-hover table-striped mb-0">
                            <thead>
                                <tr><th>Category</th><th class="text-end">Total {{ $title }}</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($data[$type] as $name => $amount)
                                    <tr>
                                        <td>{{ $name }}</td>
                                        <td class="text-end fw-semibold {{ $color }}">{{ number_format($amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-end">{{ number_format($data[$type . 'Total'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endforeach
