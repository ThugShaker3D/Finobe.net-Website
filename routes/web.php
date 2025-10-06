<?php

use App\Http\Controllers\api;
use App\Http\Controllers\admin;
use App\Http\Controllers\rbxAPIs;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\dataController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Middleware\SetClientIp;
use App\Http\Middleware\LimitRequestPerIp;
use App\Http\Middleware\ModerationMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([SetClientIp::class, LimitRequestPerIp::class, ModerationMiddleware::class])->group(function() {
    Route::domain(parse_url(env('APP_URL'), PHP_URL_HOST))->group(function() {
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

        Route::get('/forum', function() {
            return redirect('/forum/home'); // for version 1 users
        });

        Route::get('/users', [frontEnd::class, 'users']);
        Route::get('/videos', [frontEnd::class, 'videos']);
        Route::get('/create', [frontEnd::class, 'create']);
        Route::get('/trades', [frontEnd::class, 'trades']);
        Route::get('/item/{id}', [frontEnd::class, 'item']);
        Route::get('/invites', [frontEnd::class, 'invites']); // testtestestt
        Route::get('/video/{id}', [frontEnd::class, 'video']);
        Route::get('/video/data/{id}', [frontEnd::class, 'video_data']);
        Route::get('/video/thumb/{id}', [frontEnd::class, 'video_thumb']);
        Route::get('/password/reset', [frontEnd::class, 'password_reset']);
        Route::get('/friends/incoming', [frontEnd::class, 'friends_incoming']);
        Route::get('/transparency/bans', [frontEnd::class, 'transparency_bans']);
        Route::get('/email/verify/{id}/{verifyid}', [frontEnd::class, 'email_verify']);
        Route::post('/verify/email', [frontEnd::class, 'verify_email']);
        Route::post('/password/email', [frontEnd::class, 'password_email']);
        Route::match(['post', 'get'], '/', [HomeController::class, 'index']);
        Route::match(['post', 'get'], '/logout', [frontEnd::class, 'logout']);
        Route::match(['post', 'get'], '/election', [frontEnd::class, 'election']);
        Route::match(['post', 'get'], '/invites/new', [frontEnd::class, 'invites_new']);
        Route::match(['post', 'get'], '/item/{id}/settings', [frontend::class, 'item_settings']);
        Route::match(['post', 'get'], '/password/verify/{id}/{resetid}', [frontEnd::class, 'password_verify']);

        Route::prefix('legal')->group(function() {
            Route::get('/rules', [frontEnd::class, 'legal_rules']);
            Route::get('/terms', [frontEnd::class, 'legal_terms']);
            Route::get('/welcome', [frontEnd::class, 'legal_welcome']);
            Route::get('/about-us', [frontEnd::class, 'legal_about_us']);
        });

        Route::prefix('place')->group(function() {
            Route::get('/{id}', [frontEnd::class, 'place']);
            Route::match(['post', 'get'], '/{id}/settings', [frontEnd::class, 'place_settings']);
        });

        Route::prefix('app')->group(function() {
            Route::get('/inbox', [frontEnd::class, 'inbox']);
            Route::get('/places', [frontEnd::class, 'places']);
            Route::get('/character', [frontEnd::class, 'character']);
            Route::get('/inbox/sent', [frontEnd::class, 'inbox_sent']);
            Route::get('/inbox/archive', [frontEnd::class, 'inbox_archive']);
            Route::match(['post', 'get'], '/theme', [frontEnd::class, 'app_theme']);
            Route::match(['post', 'get'], '/games', [frontEnd::class, 'app_games']);
            Route::match(['post', 'get'], '/connect', [frontEnd::class, 'app_connect']);
            Route::match(['post', 'get'], '/place/new', [frontEnd::class, 'place_new']);
            Route::match(['post', 'get'], '/inbox/message', [frontEnd::class, 'inbox_message']);
            Route::match(['post', 'get'], '/inbox/compose', [frontEnd::class, 'inbox_compose']);
            Route::match(['post', 'get'], '/forum/new/post', [frontEnd::class, 'forum_new_post']);
            Route::match(['post', 'get', 'options'], '/settings', [frontEnd::class, 'app_settings']);
        });

        Route::prefix('catalog')->group(function() {
            Route::match(['post', 'get', 'options'], '/new', [frontEnd::class, 'catalog_new']);
            Route::get('/{section}', [frontEnd::class, 'catalog_index']);
            Route::get('/', fn() => redirect('/catalog/hats'));
        });

        Route::prefix('user')->group(function() {
            Route::get('/gettoken', [frontEnd::class, 'gettoken']);
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
            Route::get('/deny', [admin::class, 'deny']);
            Route::get('/places', [api::class, 'places']);
            Route::get('/accept', [admin::class, 'accept']);
            Route::get('/inventory', [api::class, 'inventory']);
            Route::post('/rate', [api::class, 'rate']);
            Route::post('/render', [api::class, 'render']);
            Route::post('/purchase', [api::class, 'purchase']);
            Route::post('/character', [api::class, 'character']);
            Route::post('/rating_number', [api::class, 'rating_number']);
            Route::match(['post', 'get'], '/connect', [frontEnd::class, 'api_connect']);

            Route::prefix('video')->group(function() {
                Route::post('/rate', [api::class, 'video_rate']);
                Route::post('/rating_number', [api::class, 'video_rating_number']);
            });
        });

        Route::prefix('auth')->group(function() {
            Route::get('/form', [frontEnd::class, 'auth_form']);
            Route::match(['post', 'get'], '/login', [frontEnd::class, 'auth_login']);
            Route::match(['post', 'get'], '/register', [frontEnd::class, 'auth_register']);
        });

        Route::prefix('admin')->group(function() {
            Route::get('/', [admin::class, 'index']);
            Route::get('/pin', [admin::class, 'pin']);
            Route::get('/lock', [admin::class, 'lock']);
            Route::get('/unpin', [admin::class, 'unpin']);
            Route::get('/stick', [admin::class, 'stick']);
            Route::get('/unlock', [admin::class, 'unlock']);
            Route::get('/assets', [admin::class, 'assets']);
            Route::get('/unstick', [admin::class, 'unstick']);
            Route::get('/decider', [admin::class, 'decider']);
            Route::match(['post', 'get'], '/warn', [admin::class, 'warn']);
            Route::match(['post', 'get'], '/bans', [admin::class, 'bans']);
            Route::match(['post', 'get'], '/servers', [admin::class, 'servers']);
            Route::match(['post', 'get'], '/elections', [admin::class, 'elections']);
            Route::match(['post', 'get'], '/give_dius', [admin::class, 'give_dius']);
            Route::match(['post', 'get'], '/createxml', [admin::class, 'createxml']);
            Route::match(['post', 'get'], '/give_badges', [admin::class, 'give_badges']);
            Route::match(['post', 'get'], '/prune-posts', [admin::class, 'prune_posts']);
            Route::match(['post', 'get'], '/rbxcreatexml', [admin::class, 'rbxcreatexml']);
            Route::match(['post', 'get'], '/announcements', [admin::class, 'announcements']);
            Route::match(['post', 'get'], '/changeversions', [admin::class, 'changeversions']);
        });
    });

    Route::domain('clientsettings.finobe.net')
        ->group(base_path('routes/services/clientsettings.php'));
    
    Route::domain('assetgame.finobe.net')
        ->group(base_path('routes/services/assetgame.php'));

    Route::domain('versioncompatibility.finobe.net')
        ->group(base_path('routes/services/versioncompatibility.php'));

    Route::domain('api.finobe.net')->group(function() {
        Route::get('/universes/validate-place-join', [rbxAPIs::class, 'validatePlaceJoin']);
        Route::any('/marketplace/productinfo', [rbxAPIs::class, 'productInfo']);
    });
});
