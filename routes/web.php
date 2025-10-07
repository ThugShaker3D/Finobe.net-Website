<?php

use App\Http\Controllers\api;
use App\Http\Controllers\admin;
use App\Http\Controllers\rbxAPIs;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ForumController;
use App\Http\Controllers\Web\UsersController;
use App\Http\Controllers\Web\GamesController;
use App\Http\Controllers\Web\TradesController;
use App\Http\Controllers\Web\AvatarController;
use App\Http\Controllers\Web\AccountController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\FriendsController;
use App\Http\Controllers\Web\InvitesController;
use App\Http\Controllers\Web\ElectionController;
use App\Http\Controllers\Web\CurrencyController;
use App\Http\Controllers\Web\MessagesController;
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

        Route::get('/forum', fn() => redirect('/forum/home'));
        Route::get('/users', [UsersController::class, 'index']);
        Route::get('/videos', [frontEnd::class, 'videos']);
        Route::get('/create', [HomeController::class, 'create']);
        Route::get('/trades', [TradesController::class, 'trades']);
        Route::get('/item/{id}', [CatalogController::class, 'item']);
        Route::get('/invites', [InvitesController::class, 'index']); // testtestestt
        Route::get('/video/{id}', [frontEnd::class, 'video']);
        Route::get('/video/data/{id}', [frontEnd::class, 'video_data']);
        Route::get('/video/thumb/{id}', [frontEnd::class, 'video_thumb']);
        Route::get('/password/reset', [frontEnd::class, 'password_reset']);
        Route::get('/friends/incoming', [FriendsController::class, 'incoming']);
        Route::get('/transparency/bans', [frontEnd::class, 'transparency_bans']);
        Route::get('/email/verify/{id}/{verifyid}', [frontEnd::class, 'email_verify']);
        Route::post('/verify/email', [AccountController::class, 'verify_email']);
        Route::post('/password/email', [frontEnd::class, 'password_email']);
        Route::match(['post', 'get'], '/', [HomeController::class, 'index']);
        Route::match(['post', 'get'], '/logout', [frontEnd::class, 'logout']);
        Route::match(['post', 'get'], '/election', [ElectionController::class, 'election']);
        Route::match(['post', 'get'], '/invites/new', [InvitesController::class, 'new']);
        Route::match(['post', 'get'], '/item/{id}/settings', [CatalogController::class, 'settings']);
        Route::match(['post', 'get'], '/password/verify/{id}/{resetid}', [frontEnd::class, 'password_verify']);

        Route::prefix('legal')->group(function() {
            Route::get('/rules', [frontEnd::class, 'legal_rules']);
            Route::get('/terms', [frontEnd::class, 'legal_terms']);
            Route::get('/welcome', [frontEnd::class, 'legal_welcome']);
            Route::get('/about-us', [frontEnd::class, 'legal_about_us']);
        });

        Route::prefix('place')->group(function() {
            Route::get('/{id}', [GamesController::class, 'place']);
            Route::match(['post', 'get'], '/{id}/settings', [GamesController::class, 'place_settings']);
        });

        Route::prefix('app')->group(function() {
            Route::get('/inbox', [MessagesController::class, 'inbox']);
            Route::get('/places', [GamesController::class, 'index']);
            Route::get('/character', [AvatarController::class, 'index']);
            Route::get('/inbox/sent', [MessagesController::class, 'sent']);
            Route::get('/inbox/archive', [MessagesController::class, 'archive']);
            Route::match(['post', 'get'], '/theme', [frontEnd::class, 'app_theme']);
            Route::match(['post', 'get'], '/games', [frontEnd::class, 'app_games']);
            Route::match(['post', 'get'], '/connect', [frontEnd::class, 'app_connect']);
            Route::match(['post', 'get'], '/place/new', [frontEnd::class, 'place_new']);
            Route::match(['post', 'get'], '/inbox/message', [MessagesController::class, 'message']);
            Route::match(['post', 'get'], '/inbox/compose', [MessagesController::class, 'compose']);
            Route::match(['post', 'get'], '/forum/new/post', [ForumController::class, 'new_post']);
            Route::match(['post', 'get', 'options'], '/settings', [AccountController::class, 'settings']);
        });

        Route::prefix('catalog')->group(function() {
            Route::match(['post', 'get', 'options'], '/new', [CatalogController::class, 'new']);
            Route::get('/{section}', [CatalogController::class, 'index']);
            Route::get('/', fn() => redirect('/catalog/hats'));
        });

        Route::prefix('user')->group(function() {
            Route::get('/gettoken', [frontEnd::class, 'gettoken']);
            Route::get('/transaction-log', [CurrencyController::class, 'transactions']);
            Route::get('/{id}', [ProfileController::class, 'index']);
            Route::get('/{id}/add', [FriendsController::class, 'add']);
            Route::get('/{id}/accept', [FriendsController::class, 'accept']);
            Route::get('/{id}/remove', [FriendsController::class, 'remove']);
            Route::get('/{id}/friends', [FriendsController::class, 'list']);
        });

        Route::prefix('forum')->group(function() {
            Route::get('/home', [ForumController::class, 'index']);
            Route::get('/home/{section}', [ForumController::class, 'section']);
            Route::get('/subscribe', [ForumController::class, 'subscribe']);
            Route::get('/search', [ForumController::class, 'search']);
            Route::match(['post', 'get'], '/post', [ForumController::class, 'post']);
            Route::match(['post', 'get'], '/edit', [ForumController::class, 'edit_reply']);
            Route::match(['post', 'get'], '/new/reply', [ForumController::class, 'reply']);
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
