<?php

use App\Http\Middleware\EnsureDeviceAuthenticated;
use App\Http\Middleware\EnsureTeacherHasHomeroomClass;
use App\Http\Middleware\EnsureUserHasAllRoles;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->statefulApi();
        
        $middleware->api(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'role.all' => EnsureUserHasAllRoles::class,
            'homeroom-class' => EnsureTeacherHasHomeroomClass::class,
            'device.auth' => EnsureDeviceAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
