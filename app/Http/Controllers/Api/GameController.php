<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WnbaPlayerGame;
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

    public function show(string $gameId, Request $request, GameScheduleService $schedule): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
            'live' => 'nullable|boolean',
        ]);

        $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
        $includeLive = $request->boolean('live', true);

        $game = collect($schedule->list($season, $includeLive))
            ->first(fn (array $row) => (string) ($row['game_id'] ?? '') === $gameId);

        if ($game === null) {
            return response()->json([
                'message' => 'Game not found',
            ], 404);
        }

        $internalId = (int) ($game['id'] ?? 0);
        if ($internalId > 0) {
            $game['box_score'] = $this->boxScoreForGame($internalId);
        } else {
            $game['box_score'] = [];
        }

        return response()->json([
            'data' => $game,
            'message' => 'Game retrieved successfully',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function boxScoreForGame(int $internalGameId): array
    {
        return WnbaPlayerGame::with(['player', 'team'])
            ->where('game_id', $internalGameId)
            ->where('did_not_play', false)
            ->orderByDesc('points')
            ->get()
            ->map(fn (WnbaPlayerGame $row) => [
                'player_name' => $row->player?->athlete_display_name,
                'team_abbreviation' => $row->team?->team_abbreviation,
                'minutes' => $row->minutes,
                'points' => $row->points,
                'rebounds' => $row->rebounds,
                'assists' => $row->assists,
                'steals' => $row->steals,
                'blocks' => $row->blocks,
                'field_goals_made' => $row->field_goals_made,
                'field_goals_attempted' => $row->field_goals_attempted,
                'three_point_field_goals_made' => $row->three_point_field_goals_made,
                'three_point_field_goals_attempted' => $row->three_point_field_goals_attempted,
                'starter' => $row->starter,
            ])
            ->values()
            ->all();
    }
}
