<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WNBA\Data\GameScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index(Request $request, GameScheduleService $schedule): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
            'live' => 'nullable|boolean',
        ]);

        $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
        $includeLive = $request->boolean('live', true);

        $games = $schedule->list($season, $includeLive);

        return response()->json([
            'data' => $games,
            'meta' => [
                'season' => $season,
                'count' => count($games),
                'live' => $includeLive,
            ],
            'message' => 'Games retrieved successfully',
        ]);
    }
}
