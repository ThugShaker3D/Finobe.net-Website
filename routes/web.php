<?php

use App\Http\Controllers\api;
use App\Http\Controllers\rbxAPIs;
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
        Route::get('/create', [frontEnd::class, 'create']);
        Route::get('/trades', [frontEnd::class, 'trades']);
        Route::get('/item/{id}', [frontEnd::class, 'item']);
        Route::get('/invites', [frontEnd::class, 'invites']);
        Route::get('/password/reset', [frontEnd::class, 'password_reset']);
        Route::get('/friends/incoming', [frontEnd::class, 'friends_incoming']);
        Route::get('/email/verify/{id}/{verifyid}', [frontEnd::class, 'verify_email']);
        Route::post('/verify/email', [frontEnd::class, 'verify_email']);
        Route::post('/password/email', [frontEnd::class, 'password_email']);
        Route::match(['post', 'get'], '/', [frontEnd::class, 'index']);
        Route::match(['post', 'get'], '/logout', [frontEnd::class, 'logout']);
        Route::match(['post', 'get'], '/election', [frontEnd::class, 'election']);
        Route::match(['post', 'get'], '/invites/new', [frontEnd::class, 'invites_new']);
        Route::match(['post', 'get'], '/password/verify/{id}/{resetid}', [frontEnd::class, 'password_verify']);

        Route::prefix('place')->group(function() {
            Route::get('/{id}', [frontEnd::class, 'place']);
            Route::match(['post', 'get'], '/{id}/settings', [frontEnd::class, 'place_settings']);
        });

        Route::prefix('app')->group(function() {
            Route::get('/places', [frontEnd::class, 'places']);
            Route::get('/character', [frontEnd::class, 'character']);
            Route::get('/inbox', [frontEnd::class, 'inbox']);
            Route::get('/inbox/sent', [frontEnd::class, 'inbox_sent']);
            Route::get('/inbox/archive', [frontEnd::class, 'inbox_archive']);
            Route::match(['post', 'get'], '/place/new', [frontEnd::class, 'place_new']);
            Route::match(['post', 'get'], '/inbox/message', [frontEnd::class, 'inbox_message']);
            Route::match(['post', 'get'], '/inbox/compose', [frontEnd::class, 'inbox_compose']);

            Route::prefix('forum')->group(function() {
                Route::match(['post', 'get'], '/new/post', [frontEnd::class, 'forum_new_post']);
            });
        });

        Route::prefix('catalog')->group(function() {
            Route::match(['post', 'get', 'options'], '/new', [frontEnd::class, 'catalog_new']);
            Route::get('/{section}', [frontEnd::class, 'catalog_index']);

            Route::get('/', function() {
                return redirect('/catalog/hats');
            });
        });

        Route::prefix('user')->group(function() {
            Route::get('/transaction-log', [frontEnd::class, 'transactions']);
            Route::get('/{id}', [frontEnd::class, 'user']);
            Route::get('/{id}/add', [frontEnd::class, 'user_add']);
            Route::get('/{id}/accept', [frontEnd::class, 'user_accept']);
            Route::get('/{id}/remove', [frontEnd::class, 'user_remove']);
            Route::get('/{id}/friends', [frontEnd::class, 'user_friends']);
        });

        Route::prefix('forum')->group(function() {
            Route::get('/home', [frontEnd::class, 'forum_home']);
            Route::get('/home/{section}', [frontEnd::class, 'forum_section']);
            Route::get('/subscribe', [frontEnd::class, 'forum_subscribe']);
            Route::get('/search', [frontEnd::class, 'forum_search']);
            Route::match(['post', 'get'], '/post', [frontEnd::class, 'forum_post']);
            Route::match(['post', 'get'], '/edit', [frontEnd::class, 'forum_edit_reply']);
            Route::match(['post', 'get'], '/new/reply', [frontEnd::class, 'forum_reply']);
        });

        Route::prefix('api')->group(function() {
            Route::get('/mark', [api::class, 'mark']); // not mathmark reference >:D
            Route::get('/places', [api::class, 'places']);
            Route::get('/inventory', [api::class, 'inventory']);
            Route::post('/rate', [api::class, 'rate']);
            Route::post('/render', [api::class, 'render']);
            Route::post('/purchase', [api::class, 'purchase']);
            Route::post('/character', [api::class, 'character']);
            Route::post('/rating_number', [api::class, 'rating_number']);
        });

        Route::prefix('auth')->group(function() {
            Route::get('/form', [frontEnd::class, 'auth_form']);
            Route::match(['post', 'get'], '/login', [frontEnd::class, 'auth_login']);
            Route::match(['post', 'get'], '/register', [frontEnd::class, 'auth_register']);
        });
    });

    Route::domain('clientsettings.finobe.net')->group(function() {
        Route::get('/Setting/QuietGet/{bucketName}', [rbxAPIs::class, 'quietGet']);
    });

    Route::domain('versioncompatibility.finobe.net')->group(function() {
        Route::get('/GetAllowedSecurityVersions', [rbxAPIs::class, 'getAllowedSecurityVersions']);
        Route::get('/GetAllowedMD5Hashes', [rbxAPIs::class, 'getAllowedMD5Hashes']);
    });

    Route::domain('api.finobe.net')->group(function() {
        Route::get('/universes/validate-place-join', [rbxAPIs::class, 'validatePlaceJoin']);
        Route::any('/marketplace/productinfo', [rbxAPIs::class, 'productInfo']);
    });

    Route::domain('www.finobe.net')->group(function() {
        Route::get('/Game/Gameserver.lua', [rbxAPIs::class, 'gameServerLua']);
        Route::any('/asset/GetScriptState.ashx', [rbxAPIs::class, 'getScriptStateAshx']);
        Route::get('/asset/', [rbxAPIs::class, 'asset']);
        Route::get('/api/gameserver/register/{jobId}', [rbxAPIs::class, 'registerJobId']);
        Route::get('/api/gameserver/visit/{jobId}', [rbxAPIs::class, 'visitJobId']);
        Route::get('/api/gameserver/shutdown/{jobId}', [rbxAPIs::class, 'shutdownJobId']);
        Route::get('/api/gameserver/alive/{jobId}', [rbxAPIs::class, 'aliveJobId']);
        Route::post('/api/gameserver/update', [rbxAPIs::class, 'update']);
        Route::get('/Asset/CharacterFetch.ashx', [rbxAPIs::class, 'characterFetch']);
        Route::get('/Asset/BodyColors.ashx', [rbxAPIs::class, 'bodyColors']);
        Route::get('/Login/Negotiate.ashx', [rbxAPIs::class, 'negotiateAshx']);
        Route::get('//Game/Studio.ashx', [rbxAPIs::class, 'studioAshx']);
        Route::any('/Game/PlaceLauncher.ashx', [rbxAPIs::class, 'placeLauncher']);
        Route::get('/Game/Join.ashx', [rbxAPIs::class, 'joinAshx']);
    });
});