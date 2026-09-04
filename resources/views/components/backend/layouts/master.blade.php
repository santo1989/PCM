<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="Support Issue Monitoring Softwear from NTG, MIS Department" />
    <meta name="author" content="Md. Hasibul Islam Santo, MIS, NTG" />
    <title> {{ $pageTitle ?? 'Personal Cost Management' }} </title>

    <!-- <link href="css/styles.css" rel="stylesheet" /> -->

    <!-- jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

    <!-- Poppins (app-wide font, matches auth pages) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- font-awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js" crossorigin="anonymous"></script>

    <!-- Bootstrap core icon -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">

    <!-- Select2 CSS + Bootstrap 5 theme -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />



    <!-- sweetalert2 cdn-->

    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <!-- DataTable -->

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />

    <!-- Custom CSS -->

    <link href="{{ asset('ui/backend/css/styles.css') }}" rel="stylesheet" />

    <!-- App theme overrides: loaded after styles.css so cascade order wins without !important -->
    <link href="{{ asset('ui/backend/css/app-theme.css') }}" rel="stylesheet" />

    <!-- Print stylesheet: reports use .no-print to hide chrome (nav, filters, buttons) when printing / saving as PDF -->
    <style>
        @media print {
            .no-print, .sb-topnav, #layoutSidenav_nav {
                display: none !important;
            }

            #layoutSidenav_content {
                margin-left: 0 !important;
            }

            body {
                background: #fff !important;
            }

            .card, .table {
                break-inside: avoid;
            }
        }
    </style>

    <!-- Push Notification -->

    <script src="{{ asset('js/push.min.js') }}"></script>

</head>

<body class="sb-nav-fixed">

    <x-backend.layouts.partials.top_bar />

    <div id="layoutSidenav">


        <x-backend.layouts.partials.sidebar />

        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">

                    {{ $breadCrumb ?? ' ' }}

                    {{ $slot ?? ' ' }}
                </div>
            </main>
            {{-- <!-- @yield('content') --> --}}

            <!-- Main content -->

            {{-- <x-backend.layouts.partials.footer /> --}}

        </div>
    </div>

    <!-- Core theme JS-->
    <script src="{{ asset('ui/backend/js/scripts.js') }}"></script>

    <!-- Bootstrap core JS-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>



    <!-- DataTable JS -->
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
    <script src="{{ asset('ui/backend/js/datatables-simple-demo.js') }}"></script>

    <!-- Select2 JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
    <script>
        // Applied to any <select class="select2"> across the app — long/searchable
        // dropdowns (category pickers, the ~19-option handcash "rules" list, etc.)
        $(function () {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        });
    </script>

</body>

</html>
