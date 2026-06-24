<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheHelper;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class TeamController extends Controller
{
    use ApiResponseTrait, CacheHelper;

    public function index(Request $request): JsonResponse
    {
        try {
            // Check if the table exists
            if (! Schema::hasTable('wnba_teams')) {
                return $this->successResponse([
                    'data' => [],
                    'meta' => ['total' => 0],
                ], 'Database is still being set up. Please try again in a few minutes.');
            }

            $query = WnbaTeam::query()->league();

            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('team_display_name', 'like', "%{$search}%")
                        ->orWhere('team_location', 'like', "%{$search}%")
                        ->orWhere('team_abbreviation', 'like', "%{$search}%");
                });
            }

            $teams = $query->orderBy('team_display_name')->get();

            return $this->successResponse([
                'data' => $teams,
                'meta' => [
                    'total' => $teams->count(),
                ],
            ], 'Teams retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving teams');
        }
    }

    public function show(string $teamId): JsonResponse
    {
        $team = WnbaTeam::where('team_id', $teamId)->first();

        if (! $team) {
            return $this->notFoundResponse('Team');
        }

        return $this->successResponse($team);
    }

    public function players(string $teamId): JsonResponse
    {
        $team = WnbaTeam::where('team_id', $teamId)->first();

        if (! $team) {
            return response()->json([
                'error' => 'Team not found',
            ], 404);
        }

        // Match wnba_player_games.team_id / wnba_game_teams.team_id — ESPN team_id string on wnba_teams, not internal id.
        $playerIds = WnbaPlayerGame::where('team_id', $team->team_id)
            ->where('did_not_play', false)
            ->distinct()
            ->pluck('player_id');

        // Get the actual player records
        $players = WnbaPlayer::whereIn('id', $playerIds)
            ->orderBy('athlete_display_name')
            ->get();

        // Add team information to each player
        $playersWithTeam = $players->map(function ($player) use ($team) {
            $playerArray = $player->toArray();
            $playerArray['current_team'] = [
                'team_id' => $team->team_id,
                'team_display_name' => $team->team_display_name,
                'team_abbreviation' => $team->team_abbreviation,
                'team_logo' => $team->team_logo,
            ];

            return $playerArray;
        });

        return response()->json([
            'data' => $playersWithTeam,
            'meta' => [
                'total' => $playersWithTeam->count(),
                'team' => $team,
            ],
        ]);
    }

    /**
     * Get teams summary for dropdowns and quick access
     */
    public function summary(): JsonResponse
    {
        $cacheKey = 'teams_summary';

        $teams = Cache::remember($cacheKey, $this->defaultCacheTtl * 2, function () {
            return WnbaTeam::select([
                'id', 'team_id', 'team_abbreviation', 'team_display_name', 'team_logo',
            ])
                ->league()
                ->orderBy('team_display_name')
                ->get();
        });

        return response()->json([
            'data' => $teams,
            'message' => 'Teams summary retrieved successfully',
        ]);
    }

    /**
     * Clear team cache
     */
    public function clearCache(): JsonResponse
    {
        $patterns = ['teams_list_*', 'teams_summary'];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        return response()->json([
            'message' => 'Team cache cleared successfully',
        ]);
    }
}
