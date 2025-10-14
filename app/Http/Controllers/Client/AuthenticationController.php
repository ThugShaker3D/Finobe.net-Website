<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthenticationController extends Controller
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

    public function requestAuth(Request $request)
    {
        return response(route('client-routes.negotiate', [ 'suggest' => Auth::user()->token ]));
    }

    public function logout(Request $request)
    {
        Auth::logout();
    }

    public function getCurrentUser(Request $request)
    {
        //Simple function.
        return Auth::id() ?? -1;
    }
}
