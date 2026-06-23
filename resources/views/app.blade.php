<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="/manifest.json">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

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
