<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Applies the stored theme before first paint so the page never
             flashes the wrong one. Keep the storage key in sync with
             resources/js/lib/theme.ts. --}}
        <script>
            (function () {
                var system = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var stored = null;

                // Storage can throw when it is unavailable (private mode), in
                // which case we fall back to the OS preference.
                try {
                    stored = localStorage.getItem('theme');
                } catch (e) {}

                document.documentElement.classList.toggle(
                    'dark',
                    stored === 'dark' || (stored !== 'light' && system)
                );
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
