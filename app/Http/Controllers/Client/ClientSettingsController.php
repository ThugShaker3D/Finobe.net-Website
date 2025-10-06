<?php
namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\RobloxUtilities;

class ClientSettingsController extends Controller
{
    /**
     * Function to get Client Fast Flags
     * @param \Illuminate\Http\Request $request
     * @param string $bucket Name of the bucket.
     * @return \Illuminate\Http\Response
     */
    public function getBucket(Request $request, string $bucket)
    {
        //TODO(Karma): Move this to database, already got controllers and db migrations, discuss that with waterboi.
        if ($bucket == "ClientSharedSettings")
            $bucket = "ClientAppSettings";

        $settings = "{}";
        switch ($bucket) {
            case "ClientAppSettings":
                $settings = file_get_contents(storage_path("rbx/fflags/PCDesktopClient_2016.json"));
            case "RCCService":
                if (RobloxUtilities::IsFinobeCloudAuthorized()) {
                    $settings = file_get_contents(storage_path("rbx/fflags/WindowsComputeCloud_2016.json"));
                }
        }

        return response($settings)->header('Content-Type', 'application/json; charset=utf-8');
    }
}
