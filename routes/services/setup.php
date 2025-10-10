<?php

use App\Http\Controllers\Client\CharacterAppearanceController;
use App\Http\Controllers\Client\GameJoinControlller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\rbxAPIs;

Route::group(['as' => 'prod-setup.'], function(){
    Route::get('/version', function () {
        return 'version-publictest';
    });

    Route::get('/version-{hash}-BootstrapperVersion.txt', function () {
        return '1, 6, 3, 172';
    });
});

?>