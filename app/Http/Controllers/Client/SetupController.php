<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    // TODO: Unfinished, connect database to it?
    public function getCurrentVersion (Request $request): string {
        return 'version-xqs0ranlc9ofKJwiJtSd';
    }

    public function getCurrentBootstrapperVersion (Request $request): string {
        return '1, 6, 3, 172';
    }

    public function getCurrentActiveCDN (Request $request): string {
        return 'prod1-setup.finobe.net';
    }

    public function getFile (Request $request, string $file) {
        $file = basename($file);
		$filePath = Storage::path('setup/' . $file);
		
		if(!file_exists($filePath))
            return response ()->json([ 'code' => 0, 'message' => 'Not found.'], 404);
		
		return response()->file($filePath);
    }
}
