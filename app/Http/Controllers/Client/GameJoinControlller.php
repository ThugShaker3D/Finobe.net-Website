<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FormatVersion;
use App\Http\Controllers\RobloxUtilities;
use App\Http\Controllers\SecurityNotary;
use App\Models\User;
use App\Services\Matchmaking\Enums\PlaceLauncherStatusCodes;
use App\Services\Matchmaking\Types\PlaceLauncherResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GameJoinControlller extends Controller
{
    public function authenticateClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
			'suggest' => ['required'],
		]);

        if($validator->fails()) {
            return response()->json(['code' => 0, 'error' => 'Bad Request'], status: 403);
		}
        $valid = $validator->valid();
        if (!User::where('token', $valid['suggest']))
        {
            return response()->json(['code'=> 0,'message'=> 'Invalid authentication token'], 403);
        }

        $authToken = User::where('token', $valid['suggest'])->first();

        Auth::login($authToken);

        return response()->json(['code' => 1, 'message' => 'Successfully authenticated client'], 200);
    }
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
            "UserName" => Auth::user()->username,
            "SeleniumTestMode" => false,
            "UserId" => Auth::id(),
            "SuperSafeChat" => false,
            "CharacterAppearance" => route('client-routes.character-fetch', ['userId' => Auth::id()]), // TODO
            "ClientTicket" => RobloxUtilities::GenerateClientTicket(Auth::id(), Auth::user()->username,route('client-routes.character-fetch', ['userId' => Auth::id()]), 'jobId-Test'),
            "GameId" => 'jobId-Test',
            "PlaceId" => 1908,
            "MeasurementUrl" => "",
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
            "characterAppearanceId" => Auth::id()
        ]);
        return response(SecurityNotary::SignScript($joinScript, FormatVersion::V2), 200);
    }
}
