<?php

use App\Http\Middleware\SetClientIp;
use App\Http\Middleware\ModerationMiddleware;
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
        $middleware->append(SetClientIp::class);
        $middleware->append(ModerationMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens([
            '/logout',
            '/marketplace/productinfo',
            '/asset/GetScriptState.ashx',
            '/Game/PlaceLauncher.ashx',
            '/api/gameserver/update'
        ]);
    })
    ->create();
