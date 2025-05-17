<?php

use App\Http\Controllers\frontEnd;
use App\Http\Middleware\SetClientIp;
use Illuminate\Support\Facades\Route;

Route::middleware([SetClientIp::class])->group(function() {
    Route::domain(str_replace('https://', '', 'sitetest1.finobe.net'/*env('APP_URL')for when release replace*/))->group(function() {
        Route::fallback(function() {
            return view('v2/404', []);
        });

        Route::get('/', [frontEnd::class, 'index']);
        Route::match(['post', 'get'], '/logout', [frontEnd::class, 'logout']);

        Route::prefix('auth')->group(function() {
            Route::match(['post', 'get'], '/login', [frontEnd::class, 'login']);
        });
    });
});