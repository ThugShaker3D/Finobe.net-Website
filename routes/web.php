<?php

use App\Http\Controllers\frontEnd;
use App\Http\Middleware\SetClientIp;
use Illuminate\Support\Facades\Route;

Route::middleware([SetClientIp::class])->group(function() {
    Route::domain(str_replace('https://', '', env('APP_URL')))->group(function() {
        Route::fallback(function() {
            return view('v2/404', []);
        });

        Route::get('/', [frontEnd::class, 'index']);
    });
});