<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VersionCompatibilityController extends Controller
{
    //TODO(Karma): Finish this controller, use web configuration table?
    function getVersions(Request $request)
	{
		return Response()->json([
			'data' => [
				''
			]
		]);
	}
	
	function getMD5Hashes(Request $request)
	{		
		return Response()->json([
			'data' => [
				''
			]
		]);
	}
}
