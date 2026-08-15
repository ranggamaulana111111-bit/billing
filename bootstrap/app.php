<?php

use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsNoc;
use App\Http\Middleware\IsTeknisiOrAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => IsAdmin::class,
            'teknisi' => IsTeknisiOrAdmin::class,
            'noc' => IsNoc::class,
        ]);

        $middleware->redirectUsersTo(function ($request) {
            $role = $request->user()?->role;

            return match ($role) {
                'teknisi' => '/teknisi/dashboard',
                'noc' => '/noc/dashboard',
                default => '/dashboard',
            };
        });

        $middleware->validateCsrfTokens(except: [
            'xendit/notification',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
