<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FormatVersion;
use App\Http\Controllers\RobloxUtilities;
use App\Http\Controllers\SecurityNotary;
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

    public function joinScript(Request $request)
    {
        $joinScript = json_encode([
            "ClientPort" => 0,
            "MachineAddress" => '127.0.0.1',
            "ServerPort" => 340,
            "PingUrl" => "",
            "PingInterval" => 20,
            "UserName" => 'notaku', // make this depend on database later
            "SeleniumTestMode" => false,
            "UserId" => 1, // make this depend on database later
            "SuperSafeChat" => false,
            "CharacterAppearance" => route('asset-game.character-fetch', ['userId' => 1]), // TODO
            "ClientTicket" => RobloxUtilities::GenerateClientTicket(1, 'notaku',route('asset-game.character-fetch', ['userId' => 1]), 'jobId-Test'),
            "GameId" => 'jobId-Test', // actually jobid not GameId purposefully misleading
            "PlaceId" => 1908, // make this depend on database later
            "MeasurementUrl" => "", // idk what this does tbh
            "WaitingForCharacterGuid" => RobloxUtilities::GenerateGUID(),
            "BaseUrl" => "https://assetgame.finobe.net/",
            "ChatStyle" => "ClassicAndBubble",
            "VendorId" => "0",
            "ScreenShotInfo" => "",
            "VideoInfo" => "",
            "CreatorId" => 2,
            "CreatorTypeEnum" => "User",
            "MembershipType" => "None",
            "AccountAge" => "0",
            "CookieStoreFirstTimePlayKey" => "rbx_evt_ftp",
            "CookieStoreFiveMinutePlayKey" => "rbx_evt_fmp",
            "CookieStoreEnabled" => true,
            "IsRobloxPlace" => 2 === 1,
            "GenerateTeleportJoin" => false,
            "IsUnknownOrUnder13" => false,
            "SessionId" => "39412c34-2f9b-436f-b19d-b8db90c2e186|00000000-0000-0000-0000-000000000000|0|190.23.103.228|8|2021-03-03T17:04:47+01:00|0|null|null",
            "DataCenterId" => 0,
            "UniverseId" => 1908,
            "BrowserTrackerId" => 0,
            "UsePortraitMode" => false,
            "FollowUserId" => 0,
            "characterAppearanceId" => 1
        ]);
        return response(SecurityNotary::SignScript($joinScript, FormatVersion::V2), 200);
    }
}
