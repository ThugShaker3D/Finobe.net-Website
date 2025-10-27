<?php

namespace App\Services\Matchmaking;

use App\Services\Matchmaking\Enums\PlaceLauncherStatusCodes;
use Illuminate\Support\Facades\Redis;

class PlaceLauncher
{
  // No matter, Loading server or "Joining"
  public static function getAvailableServer(int $placeId): array
  {
    self::createGameInstance('fuck_this_shit_i_hate_my_life', 1, '127.0.0.1', 1488, 1, PlaceLauncherStatusCodes::Loading);
    
    return [
      'job_id' => 'test',
      'server_state' => PlaceLauncherStatusCodes::Loading,
    ];
  }

  public static function createGameInstance(
    string $gameId,
    int $placeId,
    string $serverAddress,
    int $port,
    int $matchmakingContext = 1,
    int $gameState = PlaceLauncherStatusCodes::Loading,
  ): bool {
    try {
      Redis::hmset('game-instances:' . $gameId, [
        'place_id' => $placeId,
        'server_address' => $serverAddress,
        'port' => $port,
        'matchmaking_context' => $matchmakingContext,
        'server_state' => $gameState,
        'player_ids' => [1, 2, 4]
      ]);

      return true;
    }
    catch (\Exception $e)
    {
      return false;
    }
  }
}
