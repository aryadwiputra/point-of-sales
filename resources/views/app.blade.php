<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <meta name="description" content="Dikasir — sistem kasir (POS) open source untuk UMKM Indonesia. Multi-warehouse, PPN, loyalty & CRM, WhatsApp gateway, offline mode. Gratis, MIT License.">
    <meta property="og:site_name" content="Dikasir">
    <meta property="og:title" content="Dikasir — Sistem Kasir Open Source untuk UMKM">
    <meta property="og:description" content="POS gratis & open source: multi-warehouse, PPN, loyalty & CRM, WhatsApp gateway, offline mode. Laravel 13 + React 19.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}/">
    <meta property="og:image" content="{{ config('app.url') }}/images/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Dikasir — Sistem Kasir Open Source untuk UMKM">
    <meta name="twitter:description" content="POS gratis & open source: multi-warehouse, PPN, loyalty & CRM, WhatsApp gateway, offline mode.">
    <meta name="twitter:image" content="{{ config('app.url') }}/images/og-image.png">
    <link rel="manifest" href="/manifest.json">

    <title data-inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts - Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Scripts -->
    @routes
    @viteReactRefresh
    @vite('resources/js/app.jsx')
    @inertiaHead
    <style>
        body.dark {
            background-color: rgb(2 6 23);
        }

        body.light {
            background-color: rgb(248 250 252);
        }
    </style>
</head>

<body class="font-sans antialiased bg-slate-50 transition-colors duration-200" onload="setInitialTheme()">

    @inertia
    <script>
        function setInitialTheme() {
            const darkMode = localStorage.getItem('darkMode') === 'true';
            if (darkMode) {
                document.body.classList.add('dark');
                document.body.classList.remove('light');
            } else {
                document.body.classList.add('light');
                document.body.classList.remove('dark');
            }
        }
    </script>
</body>

</html>
