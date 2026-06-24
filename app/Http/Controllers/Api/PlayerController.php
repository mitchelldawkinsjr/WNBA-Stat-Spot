<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheHelper;
use App\Models\WnbaPlayer;
use App\Services\WNBA\Data\PlayerGamelogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlayerController extends Controller
{
    use ApiResponseTrait, CacheHelper;
    private const PER_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        try {
            // Check if the table exists
            if (!Schema::hasTable('wnba_players')) {
                return $this->successResponse([
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 100,
                        'total' => 0,
                        'from' => null,
                        'to' => null,
                    ]
                ], 'Database is still being set up. Please try again in a few minutes.');
            }

            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', self::PER_PAGE), 500);
            $search = $request->get('search');
            $team = $request->get('team');
            $position = $request->get('position');

            $cacheKey = "players_list_{$page}_{$perPage}_{$search}_{$team}_{$position}";

            $result = Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($perPage, $search, $team, $position) {
                $query = WnbaPlayer::select([
                    'id', 'athlete_id', 'athlete_display_name', 'athlete_position_abbreviation',
                    'athlete_jersey', 'athlete_headshot_href', 'athlete_position_name',
                    'athlete_short_name', 'created_at', 'updated_at'
                ]);

                if ($search) {
                    $query->where('athlete_display_name', 'LIKE', "%{$search}%");
                }

                if ($position) {
                    $query->where('athlete_position_abbreviation', $position);
                }

                return $query->orderBy('athlete_display_name')
                            ->paginate($perPage);
            });

            return $this->successResponse([
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem(),
                ]
            ], 'Players retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving players');
        }
    }

    public function gamelog(string $id, Request $request, PlayerGamelogService $gamelogService): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
            'last_n_games' => 'nullable|integer|min:1|max:50',
            'provider' => 'nullable|string|in:espn,tank01',
        ]);

        try {
            $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
            $lastNGames = $request->input('last_n_games') ? (int) $request->input('last_n_games') : null;

            $data = $gamelogService->fetch(
                $id,
                $season,
                $lastNGames,
                $request->input('provider'),
            );

            return $this->successResponse($data, 'Player gamelog retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving player gamelog');
        }
    }

    public function show(string $id): JsonResponse
    {
        $cacheKey = "player_detail_{$id}";

        $player = Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($id) {
            return WnbaPlayer::with([
                'playerGames' => function ($query) {
                    $query->select([
                        'id', 'player_id', 'game_id', 'team_id', 'points', 'rebounds', 'assists',
                        'field_goals_made', 'field_goals_attempted', 'three_point_field_goals_made',
                        'three_point_field_goals_attempted', 'free_throws_made', 'free_throws_attempted',
                        'steals', 'blocks', 'turnovers', 'minutes', 'created_at'
                    ])
                    ->orderBy('created_at', 'desc')
                    ->limit(20); // Only load recent games for performance
                },
                'playerGames.team:id,team_id,team_abbreviation,team_display_name,team_logo',
                'playerGames.game:id,game_id,game_date,season'
            ])
            ->where('athlete_id', $id)
            ->orWhere('espn_athlete_id', $id)
            ->orWhere('tank01_player_id', $id)
            ->first();
        });

        if (!$player) {
            return response()->json([
                'message' => 'Player not found'
            ], 404);
        }

        return response()->json([
            'data' => $player,
            'message' => 'Player retrieved successfully'
        ]);
    }

    /**
     * Get players summary for dropdowns and quick access
     */
    public function summary(): JsonResponse
    {
        try {
            // Check if the table exists
            if (!Schema::hasTable('wnba_players')) {
                return response()->json([
                    'data' => [],
                    'message' => 'Database is still being set up. Please try again in a few minutes.'
                ]);
            }

            $cacheKey = "players_summary";

            $players = Cache::remember($cacheKey, $this->defaultCacheTtl * 2, function () {
                return WnbaPlayer::select([
                    'id', 'athlete_id', 'athlete_display_name', 'athlete_position_abbreviation',
                    'athlete_position_name'
                ])
                ->orderBy('athlete_display_name')
                ->get();
            });

            return response()->json([
                'data' => $players,
                'message' => 'Players summary retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'message' => 'Database is still being set up. Please try again in a few minutes.',
                'error' => $e->getMessage()
            ], 503);
        }
    }

    /**
     * Clear player cache
     */
    public function clearCache(): JsonResponse
    {
        $patterns = ['players_list_*', 'player_detail_*', 'players_summary'];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }

        return response()->json([
            'message' => 'Player cache cleared successfully'
        ]);
    }
}
