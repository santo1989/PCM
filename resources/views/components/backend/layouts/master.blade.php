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
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <!-- Poppins (app-wide font, matches auth pages) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- font-awesome (v4-shims keeps old-style icon names like "fa-refresh" and bare "fa fa-x"
         working after the 5→6 bump, without auditing every icon usage individually) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/v4-shims.min.js" crossorigin="anonymous"></script>

    <!-- Bootstrap core icon -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- Select2 CSS + Bootstrap 5 theme -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />



    <!-- sweetalert2 cdn-->

    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <!-- DataTable -->

    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@9/dist/style.css" rel="stylesheet" />

    <!-- Custom CSS -->

    <link href="{{ asset('ui/backend/css/styles.css') }}" rel="stylesheet" />

    <!-- App theme overrides: loaded after styles.css so cascade order wins without !important -->
    <link href="{{ asset('ui/backend/css/app-theme.css') }}" rel="stylesheet" />

    <!-- Print stylesheet: reports use .no-print to hide chrome (nav, filters, buttons) when printing / saving as PDF -->
    <style>
        .print-footer {
            display: none;
        }

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

            /* Browsers skip background colors/gradients by default when printing — force them
               on, so gradient headers, stat cards, and colored table headers actually show up
               in the printed/PDF output instead of collapsing to plain black-on-white text. */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            @page {
                margin: 1.2cm;
            }

            .card, .stat-card, tr {
                break-inside: avoid;
            }

            .card {
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
            }

            table thead {
                display: table-header-group;
            }

            .print-footer {
                display: block !important;
                text-align: center;
                font-size: 10px;
                color: #6b7280;
                margin-top: 1.5rem;
                padding-top: 0.5rem;
                border-top: 1px solid #dee2e6;
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

                    <div class="print-footer">
                        {{ $pageTitle ?? 'Report' }} — Generated on {{ now()->format('d M Y, h:i A') }} by Personal Cost Management
                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9" crossorigin="anonymous"></script>
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

    <!-- Database backup: manual button + once-per-day automatic check -->
    <script>
        function runDatabaseBackup(onDone) {
            return fetch('{{ route('backup.run') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (onDone) onDone(data);
                    return data;
                });
        }

        document.getElementById('backupNowBtn')?.addEventListener('click', function () {
            Swal.fire({
                title: 'Backing up database…',
                text: 'Please wait, this can take a moment.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            runDatabaseBackup(function (data) {
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Backup complete', timer: 2000, showConfirmButton: false });
                } else {
                    Swal.fire({ icon: 'error', title: 'Backup failed', text: data.message || 'Please try again.' });
                }
            }).catch(function () {
                Swal.fire({ icon: 'error', title: 'Backup failed', text: 'Could not reach the server.' });
            });
        });

        // Once per calendar day: check quickly, and only block the UI if a backup is
        // actually still needed today. Fails open (no popup) if the DB is unreachable.
        fetch('{{ route('backup.status') }}', { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                if (!data.needed) return;

                Swal.fire({
                    title: 'Server backup and maintenance',
                    text: 'Please wait while we back up the database.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                runDatabaseBackup(function () {
                    Swal.close();
                }).catch(function () {
                    Swal.close();
                });
            })
            .catch(function () {
                // DB unreachable or request failed — fail open, no interruption.
            });
    </script>

</body>

</html>
