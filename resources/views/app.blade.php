<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Sub-path the app is served under (e.g. /skdiit), read by resources/js/utils/url.js --}}
    <meta name="base-path" content="{{ request()->getBaseUrl() }}">
    <title inertia>SKDIIT 2027</title>
    {{-- ?v= makes browsers drop an icon cached from another project on localhost --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">

    {{-- Site font (public/fonts/Mornamchi): preload the two weights every page uses --}}
    <link rel="preload" href="{{ asset('fonts/Mornamchi/WOFF2%20Files/Mornamchi-Regular.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/Mornamchi/WOFF2%20Files/Mornamchi-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>
    {{-- Here, not in app.css: under `npm run dev` the stylesheet is served by Vite and /fonts/... would point there; asset() adds the sub-path on the server.
         Light 300, Regular 400, Bold 700; medium (500) and semibold (600) use the Regular and Bold files. --}}
    <style>
        @font-face {
            font-family: 'Mornamchi';
            font-weight: 300;
            font-style: normal;
            font-display: swap;
            src: url('{{ asset('fonts/Mornamchi/WOFF2%20Files/Mornamchi-Light.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Mornamchi/WOFF%20Files/Mornamchi-Light.woff') }}') format('woff');
        }
        @font-face {
            font-family: 'Mornamchi';
            font-weight: 400;
            font-style: normal;
            font-display: swap;
            src: url('{{ asset('fonts/Mornamchi/WOFF2%20Files/Mornamchi-Regular.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Mornamchi/WOFF%20Files/Mornamchi-Regular.woff') }}') format('woff');
        }
        @font-face {
            font-family: 'Mornamchi';
            font-weight: 500;
            font-style: normal;
            font-display: swap;
            src: url('{{ asset('fonts/Mornamchi/WOFF2%20Files/Mornamchi-Regular.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Mornamchi/WOFF%20Files/Mornamchi-Regular.woff') }}') format('woff');
        }
        @font-face {
            font-family: 'Mornamchi';
            font-weight: 600;
            font-style: normal;
            font-display: swap;
            src: url('{{ asset('fonts/Mornamchi/WOFF2%20Files/Mornamchi-Bold.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Mornamchi/WOFF%20Files/Mornamchi-Bold.woff') }}') format('woff');
        }
        @font-face {
            font-family: 'Mornamchi';
            font-weight: 700;
            font-style: normal;
            font-display: swap;
            src: url('{{ asset('fonts/Mornamchi/WOFF2%20Files/Mornamchi-Bold.woff2') }}') format('woff2'),
                 url('{{ asset('fonts/Mornamchi/WOFF%20Files/Mornamchi-Bold.woff') }}') format('woff');
        }
    </style>

    {{-- Apply the saved admin theme before paint (default: light) --}}
    <script>
        try { if (localStorage.getItem('skdiit-theme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) {}
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
