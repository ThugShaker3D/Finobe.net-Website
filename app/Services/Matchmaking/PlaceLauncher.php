<?php

namespace App\Services\Matchmaking;

use App\Services\Matchmaking\Enums\PlaceLauncherStatusCodes;

class PlaceLauncher
{
    public static function getAvailableServer(int $placeId): array
    {
      return [
        'job_id' => 'test',
        'server_state' => PlaceLauncherStatusCodes::Loading,
      ];
    }
}
