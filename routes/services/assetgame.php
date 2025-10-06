<?php

use App\Http\Controllers\Client\CharacterAppearanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\rbxAPIs;

Route::group(['as' => 'asset-game.'], function(){
    Route::get('/asset', [rbxAPIs::class, 'asset'])->name('asset');
    Route::group(['prefix' => 'asset'], function() {
        Route::get('/CharacterFetch.ashx', [CharacterAppearanceController::class, 'characterFetch'])->name('character-fetch');
        Route::get('/BodyColors.ashx', [CharacterAppearanceController::class, 'bodyColors'])->name('body-colors');
    });
});

?>