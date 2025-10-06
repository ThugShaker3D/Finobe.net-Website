<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Matchmaking\Enums\PlaceLauncherStatusCodes;
use App\Services\Matchmaking\Types\PlaceLauncherResponse;
use Illuminate\Http\Request;

class GameJoinControlller extends Controller
{
    public function placeLauncher(Request $request)
    {
        return response()->json(new PlaceLauncherResponse(
            'test',
            PlaceLauncherStatusCodes::Waiting,
            '',
            '',
            '',
        ));
    }
}
