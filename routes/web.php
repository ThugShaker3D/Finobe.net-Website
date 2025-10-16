<?php

use App\Http\Controllers\api;
use App\Http\Controllers\admin;
use App\Http\Controllers\rbxAPIs;
use App\Http\Controllers\frontEnd;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\ForumController;
use App\Http\Controllers\Web\UsersController;
use App\Http\Controllers\Web\GamesController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\VideoController;
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
        Route::get('/videos', [VideoController::class, 'index']);
        Route::get('/create', [HomeController::class, 'create']);
        Route::get('/trades', [TradesController::class, 'trades']);
        Route::get('/item/{id}', [CatalogController::class, 'item']);
        Route::get('/invites', [InvitesController::class, 'index']); // testtestestt
        Route::get('/video/{id}', [VideoController::class, 'video']);
        Route::get('/video/data/{id}', [VideoController::class, 'video_data']);
        Route::get('/video/thumb/{id}', [VideoController::class, 'video_thumb']);
        Route::get('/password/reset', [AccountController::class, 'password_reset']);
        Route::get('/friends/incoming', [FriendsController::class, 'incoming']);
        Route::get('/transparency/bans', [LegalController::class, 'transparency_bans']);
        Route::get('/email/verify/{id}/{verifyid}', [AccountController::class, 'email_verify']);
        Route::post('/verify/email', [AccountController::class, 'verify_email']);
        Route::post('/password/email', [AccountController::class, 'password_email']);
        Route::match(['post', 'get'], '/', [HomeController::class, 'index']);
        Route::match(['post', 'get'], '/logout', [AccountController::class, 'logout']);
        Route::match(['post', 'get'], '/election', [ElectionController::class, 'election']);
        Route::match(['post', 'get'], '/invites/new', [InvitesController::class, 'new']);
        Route::match(['post', 'get'], '/item/{id}/settings', [CatalogController::class, 'settings']);
        Route::match(['post', 'get'], '/password/verify/{id}/{resetid}', [AccountController::class, 'password_verify']);

        Route::prefix('legal')->group(function() {
            Route::get('/rules', [LegalController::class, 'rules']);
            Route::get('/terms', [LegalController::class, 'terms']);
            Route::get('/welcome', [LegalController::class, 'welcome']);
            Route::get('/about-us', [LegalController::class, 'about_us']);
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
            Route::match(['post', 'get'], '/theme', [AccountController::class, 'theme']);
            Route::match(['post', 'get'], '/games', [AccountController::class, 'games']);
            Route::match(['post', 'get'], '/connect', [AccountController::class, 'connect']);
            Route::match(['post', 'get'], '/place/new', [GamesController::class, 'place_new']);
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
            Route::get('/gettoken', [UsersController::class, 'gettoken']);
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
            Route::get('/deny', [AdminController::class, 'deny']);
            Route::get('/places', [api::class, 'places']);
            Route::get('/accept', [AdminController::class, 'accept']);
            Route::get('/inventory', [api::class, 'inventory']);
            Route::post('/rate', [api::class, 'rate']);
            Route::post('/render', [api::class, 'render']);
            Route::post('/purchase', [api::class, 'purchase']);
            Route::post('/character', [api::class, 'character']);
            Route::post('/rating_number', [api::class, 'rating_number']);
            Route::match(['post', 'get'], '/connect', [AccountController::class, 'connect']);

            Route::prefix('video')->group(function() {
                Route::post('/rate', [VideoController::class, 'rate']);
                Route::post('/rating_number', [VideoController::class, 'rating_number']);
            });
        });

        Route::prefix('auth')->group(function() {
            Route::get('/form', [AccountController::class, 'form']);
            Route::match(['post', 'get'], '/login', [AccountController::class, 'login']);
            Route::match(['post', 'get'], '/register', [AccountController::class, 'register']);
        });

        Route::prefix('admin')->group(function() {
            Route::get('/', [AdminController::class, 'index']);
            Route::get('/pin', [AdminController::class, 'pin']);
            Route::get('/lock', [AdminController::class, 'lock']);
            Route::get('/unpin', [AdminController::class, 'unpin']);
            Route::get('/stick', [AdminController::class, 'stick']);
            Route::get('/unlock', [AdminController::class, 'unlock']);
            Route::get('/assets', [AdminController::class, 'assets']);
            Route::get('/unstick', [AdminController::class, 'unstick']);
            Route::get('/decider', [AdminController::class, 'decider']);
            Route::match(['post', 'get'], '/warn', [AdminController::class, 'warn']);
            Route::match(['post', 'get'], '/bans', [AdminController::class, 'bans']);
            Route::match(['post', 'get'], '/servers', [AdminController::class, 'servers']);
            Route::match(['post', 'get'], '/elections', [AdminController::class, 'elections']);
            Route::match(['post', 'get'], '/give_dius', [AdminController::class, 'give_dius']);
            Route::match(['post', 'get'], '/createxml', [AdminController::class, 'createxml']);
            Route::match(['post', 'get'], '/give_badges', [AdminController::class, 'give_badges']);
            Route::match(['post', 'get'], '/prune-posts', [AdminController::class, 'prune_posts']);
            Route::match(['post', 'get'], '/rbxcreatexml', [AdminController::class, 'rbxcreatexml']);
            Route::match(['post', 'get'], '/announcements', [AdminController::class, 'announcements']);
            Route::match(['post', 'get'], '/changeversions', [AdminController::class, 'changeversions']);
        });
    });

    Route::domain('clientsettings.finobe.net')
        ->group(base_path('routes/services/clientsettings.php'));
    
    Route::domain('assetgame.finobe.net')
        ->group(base_path('routes/services/assetgame.php'));

    Route::domain('www.finobe.net')
        ->group(base_path('routes/services/assetgame.php'));

    Route::domain('prod1-setup.finobe.net')
        ->group(base_path('routes/services/setup.php'));

    Route::domain('versioncompatibility.finobe.net')
        ->group(base_path('routes/services/versioncompatibility.php'));

    Route::domain('api.finobe.net')->group(function() {
        Route::get('/universes/validate-place-join', [rbxAPIs::class, 'validatePlaceJoin']);
        Route::any('/marketplace/productinfo', [rbxAPIs::class, 'productInfo']);
    });
});
