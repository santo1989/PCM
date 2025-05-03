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
 <nav class="sb-topnav navbar navbar-expand navbar-light bg-light text-white"
     style="background-image: linear-gradient(#0078D7,#0078D7,#0078D7); text-color:white;">
     <!-- Navbar Brand-->
    
     <h3 class="text-center m-3">Expence</h3>
   
     <a class="navbar-brand ps-3 pl-3" href="{{ route('home') }}"></a>
     <!-- Sidebar Toggle-->
     <button class="mr-3 btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" href="#!"><span class="bg-light"><i
             class="fas fa-bars"></i></span></button>
     <!-- Navbar Search-->
     <form class="d-none d-md-inline-block form-inline ms-auto me-0 me-md-3 my-2 my-md-0">
         <div class="input-group">

             <marquee behavior="0.05" direction="">{{ $wishMessage }} </marquee>
         </div>
     </form>
     {{-- notification bell icon with dropdown board for notifications --}}
     <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
         <li class="nav-item dropdown">
             <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button"
                 data-bs-toggle="dropdown" aria-expanded="false">
                 <i class="fa fa-bell"></i>
                 <span class="badge rounded-pill">
                     
                 </span>
             </a>
             <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">

                
             </ul>
         </li>
         <!-- Navbar-->

         <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4 ">
             <li class="nav-item dropdown">
                 <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button"
                     data-bs-toggle="dropdown" aria-expanded="false"><img
                         src="{{ asset('images/users/' . auth()->user()->picture) }}" class="rounded-circle"
                         width="50px" height="50px" alt="{{ auth()->user()->name }}"> <span class="text-light">
                         {{ auth()->user()->name ?? '' }} </span>
                 </a>
                 <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">

                     <li>
                         <form method="POST" action="{{ route('logout') }}">
                             @csrf
                             <a class="dropdown-item"
                                 onclick="event.preventDefault();
                                        this.closest('form').submit();">Logout</a>

                         </form>
                     </li>
                 </ul>
             </li>
         </ul>
         {{-- {{ Auth::user()->id }} --}}
 </nav>

 <script></script>
