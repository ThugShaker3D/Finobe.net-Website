<?php

use App\Http\Controllers\api;
use App\Http\Controllers\frontEnd;
use App\Http\Middleware\SetClientIp;
use App\Http\Middleware\ModerationMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([SetClientIp::class])->group(function() {
    Route::domain(str_replace('https://', '', 'sitetest1.finobe.net'/*env('APP_URL')for when release replace*/))->middleware([ModerationMiddleware::class])->group(function() {
        Route::fallback(function() {
            return response()->view('v2/404', [
                'data' => [
                    'embeds' => [
                        'title' => '404 - Finobe',
                        'image' => env('APP_URL') . '/s/img/finnobe3logo.png'
                    ]
                ]
            ], 404);
        });

        Route::get('/users', [frontEnd::class, 'users']);
        Route::get('/user/{id}', [frontEnd::class, 'user']);
        Route::match(['post', 'get'], '/', [frontEnd::class, 'index']);
        Route::match(['post', 'get'], '/logout', [frontEnd::class, 'logout']);

        Route::prefix('app')->group(function() {
            Route::prefix('forum')->group(function() {
                Route::match(['post', 'get'], '/new/post', [frontEnd::class, 'forum_new_post']);
            });
        });

        Route::prefix('forum')->group(function() {
            Route::get('/edit', [frontEnd::class, 'forum_edit_reply']);
            Route::get('/home', [frontEnd::class, 'forum_home']);
            Route::get('/home/{section}', [frontEnd::class, 'forum_section']);
            Route::get('/search', [frontEnd::class, 'forum_search']);
            Route::match(['post', 'get'], '/post', [frontEnd::class, 'forum_post']);
            Route::match(['post', 'get'], '/new/reply', [frontEnd::class, 'forum_reply']);
        });

        Route::prefix('api')->group(function() {
            Route::get('/inventory', [api::class, 'inventory']);
            Route::post('/rate', [api::class, 'rate']);
            Route::post('/rating_number', [api::class, 'rating_number']);
        });

        Route::prefix('auth')->group(function() {
            Route::match(['post', 'get'], '/login', [frontEnd::class, 'login']);
        });
    });
});