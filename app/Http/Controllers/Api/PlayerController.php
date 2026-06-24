<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\CacheHelper;
use App\Models\WnbaPlayer;
use App\Services\WNBA\Data\PlayerGamelogService;
use App\Services\WNBA\Data\PlayerIntelService;
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

    public function overview(string $id, Request $request, PlayerIntelService $intelService): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
        ]);

        try {
            $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
            $data = $intelService->overview($id, $season);

            return $this->successResponse($data, 'Player overview retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving player overview');
        }
    }

    public function seasonStats(string $id, Request $request, PlayerIntelService $intelService): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
        ]);

        try {
            $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
            $data = $intelService->seasonStats($id, $season);

            return $this->successResponse($data, 'Player season stats retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving player season stats');
        }
    }

    public function news(string $id, Request $request, PlayerIntelService $intelService): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $limit = $request->input('limit') ? (int) $request->input('limit') : null;
            $data = $intelService->news($id, $limit);

            return $this->successResponse($data, 'Player news retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving player news');
        }
    }

    public function injuries(string $id, Request $request, PlayerIntelService $intelService): JsonResponse
    {
        $request->validate([
            'days_back' => 'nullable|integer|min:1|max:90',
        ]);

        try {
            $daysBack = $request->input('days_back') ? (int) $request->input('days_back') : null;
            $data = $intelService->injuries($id, $daysBack);

            return $this->successResponse($data, 'Player injuries retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving player injuries');
        }
    }

    public function leaders(Request $request): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
            'min_games' => 'nullable|integer|min:1|max:40',
        ]);

        try {
            if (! Schema::hasTable('wnba_players') || ! Schema::hasTable('wnba_player_games')) {
                return $this->successResponse(['leaders' => [], 'spotlight' => null], 'Leaders unavailable');
            }

            $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
            $minGames = (int) ($request->input('min_games', 5));
            $cacheKey = "players_leaders_{$season}_{$minGames}";

            $payload = Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($season, $minGames) {
                $categories = [
                    ['column' => 'points', 'label' => 'Points Per Game', 'abbr' => 'PPG'],
                    ['column' => 'rebounds', 'label' => 'Rebounds Per Game', 'abbr' => 'RPG'],
                    ['column' => 'assists', 'label' => 'Assists Per Game', 'abbr' => 'APG'],
                ];

                $leaders = [];
                foreach ($categories as $category) {
                    $leader = $this->topSeasonLeader($season, $category['column'], $category['label'], $category['abbr'], $minGames);
                    if ($leader !== null) {
                        unset($leader['db_id']);
                        $leaders[] = $leader;
                    }
                }

                $spotlight = null;
                if ($leaders !== []) {
                    $topScorerRow = $this->topSeasonLeader($season, 'points', 'Points Per Game', 'PPG', $minGames);
                    if ($topScorerRow !== null) {
                        $averages = $this->playerSeasonAverages((int) $topScorerRow['db_id'], $season, $minGames);
                        $spotlight = [
                            'player_id' => (string) $topScorerRow['player_id'],
                            'name' => $topScorerRow['name'],
                            'headshot' => $topScorerRow['headshot'],
                            'position' => $topScorerRow['position'],
                            'team' => $this->playerLatestTeamAbbr((int) $topScorerRow['db_id']),
                            'ppg' => $averages['points'] ?? $topScorerRow['value'],
                            'rpg' => $averages['rebounds'] ?? null,
                            'apg' => $averages['assists'] ?? null,
                            'category_abbr' => 'PPG',
                        ];
                    }
                }

                return ['leaders' => $leaders, 'spotlight' => $spotlight];
            });

            return $this->successResponse($payload, 'League leaders retrieved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Retrieving league leaders');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function topSeasonLeader(int $season, string $statColumn, string $label, string $abbr, int $minGames): ?array
    {
        $allowed = ['points', 'rebounds', 'assists', 'steals', 'blocks'];
        if (! in_array($statColumn, $allowed, true)) {
            return null;
        }

        $row = DB::table('wnba_player_games as pg')
            ->join('wnba_games as g', 'g.id', '=', 'pg.game_id')
            ->join('wnba_players as p', 'p.id', '=', 'pg.player_id')
            ->where('g.season', $season)
            ->where('pg.did_not_play', false)
            ->selectRaw(
                'p.id as db_id, p.athlete_id, p.athlete_display_name, p.athlete_short_name, p.athlete_headshot_href, p.athlete_position_abbreviation, AVG(pg.'.$statColumn.') as avg_stat, COUNT(*) as games_played'
            )
            ->groupBy('p.id', 'p.athlete_id', 'p.athlete_display_name', 'p.athlete_short_name', 'p.athlete_headshot_href', 'p.athlete_position_abbreviation')
            ->havingRaw('COUNT(*) >= ?', [$minGames])
            ->orderByDesc('avg_stat')
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'db_id' => (int) $row->db_id,
            'player_id' => (string) $row->athlete_id,
            'name' => $row->athlete_short_name ?: $row->athlete_display_name,
            'headshot' => $row->athlete_headshot_href,
            'position' => $row->athlete_position_abbreviation,
            'category' => $label,
            'category_abbr' => $abbr,
            'value' => round((float) $row->avg_stat, 1),
            'games_played' => (int) $row->games_played,
        ];
    }

    /**
     * @return array<string, float|null>
     */
    private function playerSeasonAverages(int $playerDbId, int $season, int $minGames): array
    {
        $row = DB::table('wnba_player_games as pg')
            ->join('wnba_games as g', 'g.id', '=', 'pg.game_id')
            ->where('pg.player_id', $playerDbId)
            ->where('g.season', $season)
            ->where('pg.did_not_play', false)
            ->selectRaw('AVG(pg.points) as points, AVG(pg.rebounds) as rebounds, AVG(pg.assists) as assists, COUNT(*) as games_played')
            ->havingRaw('COUNT(*) >= ?', [$minGames])
            ->first();

        if (! $row) {
            return [];
        }

        return [
            'points' => round((float) $row->points, 1),
            'rebounds' => round((float) $row->rebounds, 1),
            'assists' => round((float) $row->assists, 1),
        ];
    }

    private function playerLatestTeamAbbr(int $playerDbId): ?string
    {
        $row = DB::table('wnba_player_games as pg')
            ->join('wnba_games as g', 'g.id', '=', 'pg.game_id')
            ->join('wnba_teams as t', 't.team_id', '=', 'pg.team_id')
            ->where('pg.player_id', $playerDbId)
            ->orderByDesc('g.game_date')
            ->select('t.team_abbreviation')
            ->first();

        return $row?->team_abbreviation;
    }

    public function show(string $id): JsonResponse
    {
        $cacheKey = "player_detail_{$id}";

        $player = Cache::remember($cacheKey, $this->defaultCacheTtl, function () use ($id) {
            return WnbaPlayer::with([
                'playerGames' => function ($query) {
                    $query->select('wnba_player_games.*')
                        ->join('wnba_games', 'wnba_player_games.game_id', '=', 'wnba_games.id')
                        ->orderByDesc('wnba_games.game_date')
                        ->limit(50);
                },
                'playerGames.team:id,team_id,team_abbreviation,team_display_name,team_logo',
                'playerGames.game:id,game_id,game_date,season',
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
