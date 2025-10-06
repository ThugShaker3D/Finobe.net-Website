<?php

namespace App\Services\Matchmaking\Types;

use App\Services\Matchmaking\Enums\PlaceLauncherStatusCodes;

class PlaceLauncherResponse {
    public function __construct(
        public string $jobId,
        public PlaceLauncherStatusCodes $status = PlaceLauncherStatusCodes::Waiting,
        public string $joinScriptUrl,
        public string $authenticationUrl,
        public string $authenticationTicket
    ) {}
}