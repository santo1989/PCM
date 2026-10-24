@php
    $hour = now('Asia/Dhaka')->hour;
    $greeting = $hour >= 5 && $hour < 12 ? 'Good morning' : ($hour >= 12 && $hour < 18 ? 'Good afternoon' : 'Good evening');
    $user = auth()->user();
    $budgetAlerts = \App\Services\BudgetService::alerts();
@endphp
<header class="app-topbar no-print">
    {{-- Phones/tablets: open the sidebar drawer. Desktop: collapse/expand the rail. --}}
    <button class="app-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
        aria-controls="appSidebar" aria-label="Open menu">
        <i class="bi bi-list"></i>
    </button>
    <button class="app-icon-btn d-none d-lg-inline-grid" type="button" id="sidebarCollapseBtn" aria-label="Toggle sidebar">
        <i class="bi bi-layout-sidebar"></i>
    </button>

    <a class="app-brand" href="{{ route('home') }}">
        <span class="app-brand-mark"><i class="bi bi-wallet2"></i></span>
        <span class="d-none d-sm-inline">Personal Cost</span>
    </a>

    <span class="d-none d-xl-inline small text-muted ms-2">{{ $greeting }}, {{ $user->name ?? '' }}</span>

    <div class="ms-auto d-flex align-items-center gap-1">
        <button type="button" class="app-search-trigger d-none d-md-flex me-2" data-bs-toggle="modal"
            data-bs-target="#searchPalette">
            <i class="bi bi-search"></i> Search or jump to…
            <kbd>Ctrl K</kbd>
        </button>
        <button type="button" class="app-icon-btn d-md-none" data-bs-toggle="modal" data-bs-target="#searchPalette"
            aria-label="Search">
            <i class="bi bi-search"></i>
        </button>

        @can('Admin')
            <div class="dropdown d-none d-lg-block">
                <button class="btn btn-primary btn-sm px-3 me-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-plus-lg"></i> New
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach (config('navigation.quick_add') as $item)
                        <li>
                            <a class="dropdown-item py-2" href="{{ route($item['route'], $item['params'] ?? []) }}">
                                <i class="bi {{ $item['icon'] }} me-2"></i>{{ $item['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endcan

        <button type="button" class="app-icon-btn" id="themeToggle" aria-label="Toggle dark mode" title="Toggle dark mode">
            <i class="bi bi-moon-stars"></i>
        </button>

        {{-- Budget alerts: categories at/over their monthly budget this month (live-refreshed) --}}
        <div class="dropdown" id="live-budget-bell" data-live-region>
            <button class="app-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="Budget alerts">
                <i class="bi bi-bell"></i>
                @if ($budgetAlerts->isNotEmpty())
                    <span class="badge rounded-pill bg-danger">{{ $budgetAlerts->count() }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" style="width: min(340px, 92vw);">
                <div class="px-3 py-2 border-bottom fw-semibold small">Budget alerts · {{ now()->format('F') }}</div>
                @forelse ($budgetAlerts as $row)
                    <a href="{{ route('categories.index') }}" class="dropdown-item py-2 text-wrap">
                        <div class="d-flex justify-content-between small">
                            <span class="fw-semibold">{{ $row['name'] }}</span>
                            <span class="{{ $row['percent'] >= 100 ? 'text-danger' : 'text-warning' }}">{{ $row['percent'] }}%</span>
                        </div>
                        <div class="text-muted small">
                            {{ number_format($row['spent'], 2) }} of {{ number_format($row['budget'], 2) }}
                        </div>
                    </a>
                @empty
                    <div class="px-3 py-3 small text-muted">
                        No categories near their budget.
                        <a href="{{ route('categories.index') }}">Set budgets</a>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="dropdown">
            <button class="app-icon-btn p-0 ms-1" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="Account menu">
                <img src="{{ asset('images/users/' . (optional($user)->picture ?: 'avatar.png')) }}" class="app-avatar"
                    alt="">
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2">
                    <div class="fw-semibold">{{ $user->name ?? '' }}</div>
                    <div class="small text-muted">{{ $user->role->name ?? 'User' }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="{{ route('users.show', ['user' => $user->id]) }}">
                        <i class="bi bi-person me-2"></i>Profile
                    </a>
                </li>
                <li>
                    <button type="button" class="dropdown-item" id="backupNowBtn">
                        <i class="bi bi-cloud-arrow-down me-2"></i>Backup now
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item d-none" id="installAppBtn">
                        <i class="bi bi-phone me-2"></i>Install app
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
