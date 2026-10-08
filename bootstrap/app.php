<?php

use App\Http\Middleware\EnsureModule;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'module' => EnsureModule::class,
        ]);

        // Guests hitting protected pages go home with the login modal open
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn ($request) => $request->user()->canAccessBackend() ? '/admin' : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
