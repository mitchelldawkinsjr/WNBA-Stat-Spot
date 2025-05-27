<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WnbaPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlayerController extends Controller
{
    private const CACHE_TTL = 1800; // 30 minutes
    private const PER_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        try {
            // Check if the table exists
            if (!Schema::hasTable('wnba_players')) {
                return response()->json([
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 100,
                        'total' => 0,
                        'from' => null,
                        'to' => null,
                    ],
                    'message' => 'Database is still being set up. Please try again in a few minutes.'
                ]);
            }

            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', self::PER_PAGE), 500);
            $search = $request->get('search');
            $team = $request->get('team');
            $position = $request->get('position');

            $cacheKey = "players_list_{$page}_{$perPage}_{$search}_{$team}_{$position}";

            $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($perPage, $search, $team, $position) {
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

            return response()->json([
                'data' => $result->items(),
                'meta' => [
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                    'per_page' => $result->perPage(),
                    'total' => $result->total(),
                    'from' => $result->firstItem(),
                    'to' => $result->lastItem(),
                ],
                'message' => 'Players retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 100,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
                'message' => 'Database is still being set up. Please try again in a few minutes.',
                'error' => $e->getMessage()
            ], 503);
        }
    }

    public function show(string $id): JsonResponse
    {
        $cacheKey = "player_detail_{$id}";

        $player = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
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

            $players = Cache::remember($cacheKey, self::CACHE_TTL * 2, function () {
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
