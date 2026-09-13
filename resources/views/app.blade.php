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

        {{-- No SVG here on purpose: the mark is drawn artwork rather than a
             vector, so an SVG could only wrap the same pixels — and a browser
             that finds one prefers it, which would replace the 16px drawn for
             a tab with a downscale of a much larger image. --}}
        <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
        <link rel="icon" href="/favicon-96x96.png" type="image/png" sizes="96x96">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#33a1f7">

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
