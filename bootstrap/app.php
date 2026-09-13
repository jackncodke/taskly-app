<?php

use App\DeadlineClock;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The timezone cookie is written by JavaScript, so the framework must
        // read it back as it was written instead of trying to decrypt it. It
        // carries nothing private — the name of a zone the browser already
        // hands to any script on the page.
        $middleware->encryptCookies(except: [DeadlineClock::TIMEZONE_COOKIE]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The api group ships without a limit of its own. A token is cheap to
        // replay, so the ceiling is set here rather than left to whatever the
        // web server happens to allow; the limiter itself is defined in
        // AppServiceProvider.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
