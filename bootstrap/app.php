<?php

use App\Http\Middleware\AppointmentAccess;
use App\Http\Middleware\Permission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['permission' => Permission::class, 'can-appointments' => AppointmentAccess::class]);
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            $message = 'This record was already saved or conflicts with another record. Refresh the page and check before trying again.';

            return $request->expectsJson() ? response()->json(['message' => $message], 409) : back()->withErrors(['record' => $message]);
        });
    })->create();
