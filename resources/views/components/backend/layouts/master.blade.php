<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="description" content="Personal Cost Management — track income, expenses, cash and budgets" />
    <meta name="author" content="Md. Hasibul Islam Santo" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="theme-color" content="#4f46e5" />
    <title>{{ trim($pageTitle ?? '') ?: 'Personal Cost Management' }}</title>

    {{-- Apply the saved/system theme before first paint so dark mode never flashes light --}}
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('pcm-theme'); } catch (e) {}
            if (t !== 'light' && t !== 'dark') {
                t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', t);
            try {
                if (localStorage.getItem('pcm-sidebar') === 'collapsed') {
                    document.documentElement.classList.add('sidebar-collapsed-pending');
                }
            } catch (e) {}
        })();
    </script>

    {{-- PWA --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/pwa/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/pwa/icon-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9/dist/style.css" />
    <link rel="stylesheet" href="{{ asset('ui/backend/css/app-theme.css') }}?v={{ @filemtime(public_path('ui/backend/css/app-theme.css')) }}">

    {{-- Loaded in <head> because page views use jQuery / Swal / FontAwesome in inline scripts --}}
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    {{-- v4-shims keeps old icon names ("fa fa-refresh") used across page views working --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/v4-shims.min.js" crossorigin="anonymous"></script>
    <script src="{{ asset('js/push.min.js') }}"></script>
</head>

<body class="app">
    <script>
        if (document.documentElement.classList.contains('sidebar-collapsed-pending')) {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>

    <a href="#app-main" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-primary" style="z-index:2000">
        Skip to content
    </a>

    <x-backend.layouts.partials.top_bar />
    <x-backend.layouts.partials.sidebar />

    <main id="app-main" class="app-main">
        {{ $breadCrumb ?? '' }}

        <x-backend.budget-flash />

        {{ $slot ?? '' }}

        <div class="print-footer">
            {{ trim($pageTitle ?? '') ?: 'Report' }} — Generated on {{ now()->format('d M Y, h:i A') }} by Personal Cost Management
        </div>
    </main>

    <x-backend.layouts.partials.bottom_nav />
    <x-backend.layouts.partials.search_palette />

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

    <script>
        window.PCM = {
            routes: {
                backupRun: @json(route('backup.run')),
                backupStatus: @json(route('backup.status')),
                search: @json(route('search')),
            },
            serviceWorker: @json(asset('sw.js')),
        };
        window.LIVE_SIG_URL = @json(route('live.signature'));
    </script>
    <script src="{{ asset('ui/backend/js/app.js') }}?v={{ @filemtime(public_path('ui/backend/js/app.js')) }}"></script>
    <script src="{{ asset('js/live-index.js') }}?v={{ @filemtime(public_path('js/live-index.js')) }}"></script>
</body>

</html>
