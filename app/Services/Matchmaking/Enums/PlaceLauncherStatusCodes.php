<?php

namespace App\Services\Matchmaking\Enums;

enum PlaceLauncherStatusCodes: int
    {
        case Waiting = 0;
        case Loading = 1;
        case Joining = 2;
        case Disabled = 3;
        case Error = 4;
        case GameEnded = 5;
        case GameFull = 6;
        case UserLeft = 10;
        case Restricted = 11;
    }
