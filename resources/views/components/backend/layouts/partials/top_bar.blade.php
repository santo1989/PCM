 @php
     use Carbon\Carbon;
     date_default_timezone_set('Asia/Dhaka');
     $current_time = Carbon::now();
     $time_of_day = '';
     if ($current_time->hour >= 5 && $current_time->hour < 12) {
         $time_of_day = 'Morning';
     } elseif ($current_time->hour >= 12 && $current_time->hour < 18) {
         $time_of_day = 'Afternoon';
     } else {
         $time_of_day = 'Evening';
     }
     $wishMessage = "Good $time_of_day";

 @endphp
<nav class="sb-topnav navbar navbar-expand navbar-dark">
    <!-- Sidebar Toggle-->
    <button class="btn btn-link btn-sm order-1 order-lg-0 me-3" id="sidebarToggle" href="#!">
        <span><i class="fas fa-bars"></i></span>
    </button>

    <!-- Navbar Brand-->
    <a class="navbar-brand ps-1" href="{{ route('home') }}">
        <i class="bi bi-wallet2 me-2"></i>Personal Cost Management
    </a>

    <span class="d-none d-md-inline-block ms-3 small text-white-50">{{ $wishMessage }}, {{ auth()->user()->name ?? '' }}</span>

    <ul class="navbar-nav me-2 d-none d-sm-flex">
        <li class="nav-item">
            <button type="button" id="backupNowBtn" class="btn btn-sm btn-outline-light" title="Back up the database now">
                <i class="bi bi-cloud-arrow-down"></i> Backup Now
            </button>
        </li>
    </ul>

    {{-- ms-auto lives here (not on the "Backup Now" block above) so the notification bell
         and user menu stay pinned to the right edge even when Backup Now is hidden
         below the sm breakpoint — margins on a display:none element have no effect. --}}
    {{-- notification bell icon with dropdown board for notifications --}}
    <ul class="navbar-nav ms-auto me-3 me-lg-4">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" id="notificationsDropdown" href="#" role="button"
                data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <span class="badge rounded-pill"></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">
            </ul>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" id="userMenuDropdown" href="#" role="button"
                data-bs-toggle="dropdown" aria-expanded="false">
                <img src="{{ asset('images/users/' . (auth()->user()->picture ?: 'avatar.png')) }}" class="rounded-circle me-2"
                    width="36" height="36" alt="{{ auth()->user()->name }}">
                <span class="text-light">{{ auth()->user()->name ?? '' }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); this.closest('form').submit();">
                            <i class="bi bi-box-arrow-right me-1"></i> Logout
                        </a>
                    </form>
                </li>
            </ul>
        </li>
    </ul>
</nav>
