<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Apply the stored theme before first paint to avoid a
             light/dark flash (the React theme hook takes over after). --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('agent-finance.theme');
                    var preference = stored === 'light' || stored === 'dark' ? stored : 'system';
                    var dark = preference === 'dark' ||
                        (preference === 'system' &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (error) {
                    /* storage unavailable — default to light */
                }
            })();
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
