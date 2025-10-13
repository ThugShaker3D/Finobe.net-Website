<?php

use App\Http\Controllers\Client\CharacterAppearanceController;
use App\Http\Controllers\Client\GameJoinControlller;
use App\Http\Controllers\Client\LuaWebServiceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\rbxAPIs;

Route::group(['as' => 'client-routes.'], function(){
    Route::get('/asset', [rbxAPIs::class, 'asset'])->name('asset');
    Route::get('/Asset', [rbxAPIs::class, 'asset']);
    Route::group(['prefix' => 'asset'], function() {
        Route::get('/CharacterFetch.ashx', [CharacterAppearanceController::class, 'characterFetch'])->name('character-fetch');
        Route::get('/BodyColors.ashx', [CharacterAppearanceController::class, 'bodyColors'])->name('body-colors');
    });

    Route::group(['prefix'=> 'game', 'as'=> 'game.'], function() { 
        Route::match(['get','post'],'PlaceLauncher.ashx', [GameJoinControlller::class, 'placeLauncher'])->name('place-launcher');
        Route::get('Join.ashx', [GameJoinControlller::class,'joinScript'])->name('join-script');
        Route::get('LuaWebService/HandleSocialRequest.ashx', [LuaWebServiceController::class,'handleSocialRequest'])->name('lua-web-service');
    });

    Route::get('/penelope/login/negotiate', [GameJoinControlller::class,'authenticateClient'])->name('negotiate');
});

?>