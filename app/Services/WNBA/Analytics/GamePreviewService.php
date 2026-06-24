<?php

namespace App\Services\WNBA\Analytics;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\DataAggregatorService;
use App\Services\WNBA\Data\GameScheduleService;
use App\Services\WNBA\Data\Support\TeamCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GamePreviewService
{
    private const CACHE_TTL = 1800;
    private const HOME_COURT_RATING_BOOST = 2.5;
    private const KEY_PLAYER_COUNT = 5;

    public function __construct(
        private TeamAnalyticsService $teamAnalytics,
        private DataAggregatorService $dataAggregator,
        private GameScheduleService $schedule,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPreview(string $externalGameId, int $season): array
    {
        $cacheKey = "game_preview_{$externalGameId}_{$season}";

        $preview = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($externalGameId, $season) {
            return $this->generatePreview($externalGameId, $season);
        });

        if (isset($preview['error']) && ! isset($preview['home_team'])) {
            Cache::forget($cacheKey);
        }

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    private function generatePreview(string $externalGameId, int $season): array
    {
        $game = $this->resolveGame($externalGameId, $season);

        if ($game === null) {
            return ['error' => 'Game not found'];
        }

        $game = $this->enrichTeamIds($game);

        $homeTeamId = (int) ($game['home_team']['id'] ?? 0);
        $awayTeamId = (int) ($game['away_team']['id'] ?? 0);

        if ($homeTeamId <= 0 || $awayTeamId <= 0) {
            return [
                'error' => 'Team data unavailable for preview',
                'game' => $this->formatGameMeta($game),
            ];
        }

        try {
            $homeTeam = $this->buildTeamPreview($homeTeamId, $awayTeamId, $season, true);
            $awayTeam = $this->buildTeamPreview($awayTeamId, $homeTeamId, $season, false);
            $headToHead = $this->buildHeadToHead($homeTeamId, $awayTeamId, $season);
            $prediction = $this->generatePrediction($homeTeam, $awayTeam, $headToHead, $game);
            $analysis = $this->generateAnalysis($homeTeam, $awayTeam, $headToHead, $prediction);

            return [
                'game' => $this->formatGameMeta($game),
                'home_team' => $homeTeam,
                'away_team' => $awayTeam,
                'head_to_head' => $headToHead,
                'comparison' => $this->buildComparisonData($homeTeam, $awayTeam),
                'prediction' => $prediction,
                'analysis' => $analysis,
                'generated_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::error('Game preview generation failed', [
                'game_id' => $externalGameId,
                'error' => $e->getMessage(),
            ]);

            return [
                'error' => 'Failed to generate game preview',
                'game' => $this->formatGameMeta($game),
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveGame(string $externalGameId, int $season): ?array
    {
        $fromLive = collect($this->schedule->list($season, true))
            ->first(fn (array $row) => (string) ($row['game_id'] ?? '') === $externalGameId);

        if ($fromLive !== null) {
            return $fromLive;
        }

        $dbGame = WnbaGame::with(['gameTeams.team', 'gameTeams.opponentTeam'])
            ->where('game_id', $externalGameId)
            ->where('season', $season)
            ->first();

        if ($dbGame !== null) {
            return $this->transformDbGameForPreview($dbGame);
        }

        return collect($this->schedule->list($season, false))
            ->first(fn (array $row) => (string) ($row['game_id'] ?? '') === $externalGameId);
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    private function enrichTeamIds(array $game): array
    {
        foreach (['home_team', 'away_team'] as $side) {
            if (! is_array($game[$side] ?? null)) {
                continue;
            }

            $resolved = $this->resolveInternalTeamId($game[$side]);
            if ($resolved === null) {
                continue;
            }

            $game[$side]['id'] = $resolved['id'];
            $game[$side] = array_merge($game[$side], $resolved['info']);
        }

        return $game;
    }

    /**
     * @param  array<string, mixed>  $teamInfo
     * @return array{id: int, info: array<string, mixed>}|null
     */
    private function resolveInternalTeamId(array $teamInfo): ?array
    {
        $internalId = (int) ($teamInfo['id'] ?? 0);
        if ($internalId > 0) {
            $team = WnbaTeam::query()->league()->find($internalId);
            if ($team !== null) {
                return ['id' => $team->id, 'info' => $this->formatTeamInfo($team, $teamInfo)];
            }
        }

        $externalId = (string) ($teamInfo['team_id'] ?? '');
        if ($externalId !== '' && TeamCatalog::isLeagueTeamId($externalId)) {
            $team = WnbaTeam::query()
                ->league()
                ->where(function ($query) use ($externalId) {
                    $query->where('team_id', $externalId)
                        ->orWhere('espn_team_id', $externalId);
                })
                ->first();

            if ($team !== null) {
                return ['id' => $team->id, 'info' => $this->formatTeamInfo($team, $teamInfo)];
            }
        }

        $abbr = TeamCatalog::canonicalAbbreviation((string) ($teamInfo['abbreviation'] ?? ''));
        if ($abbr !== '') {
            $aliases = TeamCatalog::aliasesFor($abbr);
            $team = WnbaTeam::query()
                ->league()
                ->whereIn('team_abbreviation', $aliases)
                ->first();

            if ($team !== null) {
                return ['id' => $team->id, 'info' => $this->formatTeamInfo($team, $teamInfo)];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function formatTeamInfo(WnbaTeam $team, array $fallback = []): array
    {
        return [
            'team_id' => $team->team_id,
            'name' => $team->team_display_name ?? $team->team_name ?? ($fallback['name'] ?? 'Unknown'),
            'abbreviation' => $team->team_abbreviation ?? ($fallback['abbreviation'] ?? ''),
            'logo' => $team->team_logo ?? ($fallback['logo'] ?? null),
            'score' => $fallback['score'] ?? null,
            'winner' => $fallback['winner'] ?? false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDbGameForPreview(WnbaGame $game): array
    {
        $homeLine = $game->gameTeams->firstWhere('home_away', 'home');
        $awayLine = $game->gameTeams->firstWhere('home_away', 'away');

        return [
            'id' => $game->id,
            'game_id' => $game->game_id,
            'season' => (string) $game->season,
            'season_type' => $game->season_type,
            'game_date' => $game->game_date?->format('Y-m-d'),
            'game_date_time' => $game->game_date_time?->toIso8601String(),
            'venue_name' => $game->venue_name,
            'venue_city' => $game->venue_city,
            'venue_state' => $game->venue_state,
            'status_name' => $game->status_name,
            'home_team' => $this->teamInfoFromLine($homeLine),
            'away_team' => $this->teamInfoFromLine($awayLine),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function teamInfoFromLine(?WnbaGameTeam $line): ?array
    {
        if ($line === null) {
            return null;
        }

        $team = $line->team ?? WnbaTeam::query()->league()->find($line->team_id);

        if ($team === null) {
            $resolved = $this->resolveInternalTeamId([
                'id' => $line->team_id,
                'team_id' => (string) $line->team_id,
            ]);

            if ($resolved !== null) {
                $team = WnbaTeam::find($resolved['id']);
            }
        }

        if ($team === null) {
            return null;
        }

        return [
            'id' => $team->id,
            'team_id' => $team->team_id,
            'name' => $team->team_display_name ?? $team->team_name,
            'abbreviation' => $team->team_abbreviation,
            'logo' => $team->team_logo,
            'score' => $line->team_score,
            'winner' => $line->team_winner,
        ];
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    private function formatGameMeta(array $game): array
    {
        return [
            'id' => $game['id'] ?? null,
            'game_id' => $game['game_id'] ?? null,
            'season' => $game['season'] ?? null,
            'season_type' => $game['season_type'] ?? null,
            'game_date' => $game['game_date'] ?? null,
            'game_date_time' => $game['game_date_time'] ?? null,
            'status_name' => $game['status_name'] ?? null,
            'venue_name' => $game['venue_name'] ?? null,
            'venue_city' => $game['venue_city'] ?? null,
            'venue_state' => $game['venue_state'] ?? null,
            'home_team' => $game['home_team'] ?? null,
            'away_team' => $game['away_team'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTeamPreview(int $teamId, int $opponentId, int $season, bool $isHome): array
    {
        $team = WnbaTeam::find($teamId);
        $metrics = $this->teamAnalytics->getTeamPerformanceMetrics($teamId, $season);
        $defense = $this->teamAnalytics->getDefensiveMetrics($teamId, $season);
        $shootingTrends = $this->teamAnalytics->getShootingTrends($teamId, $season, 10);
        $recentGames = $this->getRecentGameLog($teamId, $season, 10);
        $keyPlayers = $this->getKeyPlayers($teamId, $season, $opponentId);

        $basic = $metrics['basic_stats'] ?? [];
        $splits = $metrics['home_away_splits'] ?? [];
        $contextSplit = $isHome ? ($splits['home'] ?? []) : ($splits['away'] ?? []);

        return [
            'team_id' => $teamId,
            'team_external_id' => $team?->team_id,
            'name' => $team?->team_display_name ?? $team?->team_name ?? 'Unknown',
            'abbreviation' => $team?->team_abbreviation ?? '',
            'logo' => $team?->team_logo,
            'is_home' => $isHome,
            'record' => [
                'wins' => $basic['wins'] ?? 0,
                'losses' => $basic['losses'] ?? 0,
                'win_pct' => $basic['win_percentage'] ?? 0,
            ],
            'season_stats' => $basic,
            'advanced_stats' => $metrics['advanced_stats'] ?? [],
            'efficiency' => $metrics['efficiency_ratings'] ?? [],
            'pace' => $metrics['pace_metrics'] ?? [],
            'defense' => $defense,
            'home_away_splits' => $splits,
            'context_split' => $contextSplit,
            'recent_form' => $metrics['recent_form'] ?? [],
            'recent_games' => $recentGames,
            'shooting_trends' => array_reverse($shootingTrends),
            'key_players' => $keyPlayers,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getRecentGameLog(int $teamId, int $season, int $limit): array
    {
        return WnbaGameTeam::query()
            ->with(['game', 'opponentTeam'])
            ->where('team_id', $teamId)
            ->whereHas('game', fn ($q) => $q->where('season', $season))
            ->join('wnba_games', 'wnba_games.id', '=', 'wnba_game_teams.game_id')
            ->orderByDesc('wnba_games.game_date')
            ->select('wnba_game_teams.*')
            ->limit($limit)
            ->get()
            ->map(fn (WnbaGameTeam $row) => [
                'date' => optional($row->game?->game_date)->format('M j') ?? '',
                'opponent' => $row->opponentTeam?->team_abbreviation ?? 'OPP',
                'points_scored' => (int) $row->team_score,
                'points_allowed' => (int) $row->opponent_team_score,
                'result' => $row->team_winner ? 'W' : 'L',
                'home_away' => $row->home_away,
                'point_differential' => (int) $row->team_score - (int) $row->opponent_team_score,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getKeyPlayers(int $teamId, int $season, int $opponentId): array
    {
        $leaders = WnbaPlayerGame::query()
            ->join('wnba_games', 'wnba_games.id', '=', 'wnba_player_games.game_id')
            ->join('wnba_players', 'wnba_players.id', '=', 'wnba_player_games.player_id')
            ->where('wnba_player_games.team_id', $teamId)
            ->where('wnba_games.season', $season)
            ->where('wnba_player_games.did_not_play', false)
            ->where('wnba_player_games.minutes', '>', 0)
            ->groupBy(
                'wnba_player_games.player_id',
                'wnba_players.athlete_display_name',
                'wnba_players.athlete_position_abbreviation',
                'wnba_players.athlete_headshot_href'
            )
            ->select([
                'wnba_player_games.player_id',
                'wnba_players.athlete_display_name as name',
                'wnba_players.athlete_position_abbreviation as position',
                'wnba_players.athlete_headshot_href as headshot',
                DB::raw('COUNT(*) as games_played'),
                DB::raw('ROUND(AVG(wnba_player_games.points), 1) as ppg'),
                DB::raw('ROUND(AVG(wnba_player_games.rebounds), 1) as rpg'),
                DB::raw('ROUND(AVG(wnba_player_games.assists), 1) as apg'),
                DB::raw('ROUND(AVG(wnba_player_games.minutes), 1) as mpg'),
            ])
            ->orderByDesc('ppg')
            ->limit(self::KEY_PLAYER_COUNT)
            ->get();

        return $leaders->map(function ($leader) use ($season, $opponentId) {
            $recent = $this->dataAggregator->aggregatePlayerData(
                (int) $leader->player_id,
                $season,
                5
            );

            $vsOpponent = WnbaPlayerGame::query()
                ->join('wnba_games', 'wnba_games.id', '=', 'wnba_player_games.game_id')
                ->join('wnba_game_teams as opp_gt', function ($join) use ($opponentId) {
                    $join->on('opp_gt.game_id', '=', 'wnba_games.id')
                        ->where('opp_gt.team_id', '=', $opponentId);
                })
                ->where('wnba_player_games.player_id', $leader->player_id)
                ->where('wnba_games.season', $season)
                ->where('wnba_player_games.did_not_play', false)
                ->select([
                    DB::raw('ROUND(AVG(wnba_player_games.points), 1) as ppg'),
                    DB::raw('COUNT(*) as games'),
                ])
                ->first();

            $recentAverages = $recent['season_stats']['averages'] ?? [];
            $recentForm = $recent['performance_trends'] ?? [];

            return [
                'player_id' => (int) $leader->player_id,
                'name' => $leader->name,
                'position' => $leader->position,
                'headshot' => $leader->headshot,
                'season' => [
                    'games_played' => (int) $leader->games_played,
                    'ppg' => (float) $leader->ppg,
                    'rpg' => (float) $leader->rpg,
                    'apg' => (float) $leader->apg,
                    'mpg' => (float) $leader->mpg,
                ],
                'last_5' => [
                    'ppg' => $recentAverages['points'] ?? (float) $leader->ppg,
                    'rpg' => $recentAverages['rebounds'] ?? (float) $leader->rpg,
                    'apg' => $recentAverages['assists'] ?? (float) $leader->apg,
                    'trend' => $recentForm['trend_direction'] ?? 'stable',
                ],
                'vs_opponent' => [
                    'ppg' => $vsOpponent ? (float) ($vsOpponent->ppg ?? 0) : null,
                    'games' => $vsOpponent ? (int) ($vsOpponent->games ?? 0) : 0,
                ],
                'game_log' => array_slice($recent['game_log'] ?? [], 0, 5),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHeadToHead(int $homeTeamId, int $awayTeamId, int $season): array
    {
        $games = WnbaGame::query()
            ->whereHas('gameTeams', fn ($q) => $q->where('team_id', $homeTeamId))
            ->whereHas('gameTeams', fn ($q) => $q->where('team_id', $awayTeamId))
            ->where('season', '>=', $season - 1)
            ->with(['gameTeams.team'])
            ->orderByDesc('game_date')
            ->limit(10)
            ->get();

        if ($games->isEmpty()) {
            return [
                'total_games' => 0,
                'home_team_wins' => 0,
                'away_team_wins' => 0,
                'avg_total_points' => 0,
                'avg_margin' => 0,
                'recent_meetings' => [],
            ];
        }

        $homeWins = 0;
        $awayWins = 0;
        $totalPoints = 0;
        $margins = [];
        $meetings = [];

        foreach ($games as $game) {
            $homeLine = $game->gameTeams->firstWhere('team_id', $homeTeamId);
            $awayLine = $game->gameTeams->firstWhere('team_id', $awayTeamId);

            if (!$homeLine || !$awayLine) {
                continue;
            }

            if ($homeLine->team_winner) {
                $homeWins++;
            } else {
                $awayWins++;
            }

            $gameTotal = (int) $homeLine->team_score + (int) $awayLine->team_score;
            $totalPoints += $gameTotal;
            $margins[] = (int) $homeLine->team_score - (int) $awayLine->team_score;

            $meetings[] = [
                'date' => optional($game->game_date)->format('M j, Y') ?? '',
                'season' => $game->season,
                'home_score' => (int) $homeLine->team_score,
                'away_score' => (int) $awayLine->team_score,
                'home_away' => $homeLine->home_away === 'home' ? 'home' : 'away',
                'winner' => $homeLine->team_winner ? 'home' : 'away',
            ];
        }

        $gameCount = count($meetings);

        return [
            'total_games' => $gameCount,
            'home_team_wins' => $homeWins,
            'away_team_wins' => $awayWins,
            'avg_total_points' => $gameCount > 0 ? round($totalPoints / $gameCount, 1) : 0,
            'avg_margin' => $gameCount > 0 ? round(array_sum($margins) / $gameCount, 1) : 0,
            'recent_meetings' => $meetings,
        ];
    }

    /**
     * @param  array<string, mixed>  $homeTeam
     * @param  array<string, mixed>  $awayTeam
     * @param  array<string, mixed>  $headToHead
     * @param  array<string, mixed>  $game
     * @return array<string, mixed>
     */
    private function generatePrediction(array $homeTeam, array $awayTeam, array $headToHead, array $game): array
    {
        $homeEff = $homeTeam['efficiency'] ?? [];
        $awayEff = $awayTeam['efficiency'] ?? [];
        $homeNet = (float) ($homeEff['net_rating'] ?? 0);
        $awayNet = (float) ($awayEff['net_rating'] ?? 0);

        $homeContextWinPct = (float) ($homeTeam['context_split']['win_pct'] ?? 0.5);
        $awayContextWinPct = (float) ($awayTeam['context_split']['win_pct'] ?? 0.5);

        $homeL5Wins = (int) ($homeTeam['recent_form']['last_5']['wins'] ?? 0);
        $awayL5Wins = (int) ($awayTeam['recent_form']['last_5']['wins'] ?? 0);

        $contextBoost = ($homeContextWinPct - $awayContextWinPct) * 6;
        $formBoost = ($homeL5Wins - $awayL5Wins) * 0.6;

        $h2hBoost = 0.0;
        if (($headToHead['total_games'] ?? 0) >= 2) {
            $h2hBoost = (($headToHead['home_team_wins'] ?? 0) - ($headToHead['away_team_wins'] ?? 0)) * 0.4;
        }

        $ratingEdge = ($homeNet - $awayNet) + self::HOME_COURT_RATING_BOOST + $contextBoost + $formBoost + $h2hBoost;
        $projectedSpread = round($ratingEdge * 0.35, 1);

        $homePace = (float) ($homeTeam['pace']['pace'] ?? 75);
        $awayPace = (float) ($awayTeam['pace']['pace'] ?? 75);
        $avgPace = ($homePace + $awayPace) / 2;

        $homeOff = (float) ($homeEff['offensive_rating'] ?? 100);
        $homeDef = (float) ($homeEff['defensive_rating'] ?? 100);
        $awayOff = (float) ($awayEff['offensive_rating'] ?? 100);
        $awayDef = (float) ($awayEff['defensive_rating'] ?? 100);

        $projectedHomeScore = round((($homeOff + $awayDef) / 2) * ($avgPace / 100), 1);
        $projectedAwayScore = round((($awayOff + $homeDef) / 2) * ($avgPace / 100), 1);

        // Adjust scores to align with spread while keeping total reasonable
        $midpoint = ($projectedHomeScore + $projectedAwayScore) / 2;
        $projectedHomeScore = round($midpoint + ($projectedSpread / 2), 1);
        $projectedAwayScore = round($midpoint - ($projectedSpread / 2), 1);

        $winProbHome = round(100 / (1 + exp(-$projectedSpread / 5.5)), 1);
        $winProbAway = round(100 - $winProbHome, 1);

        $predictedWinner = $winProbHome >= 50 ? 'home' : 'away';
        $confidence = round(min(abs($winProbHome - 50) * 2, 95), 1);

        $factors = [
            ['factor' => 'Net rating edge', 'edge' => round($homeNet - $awayNet, 1), 'favors' => $this->favorsSide($homeNet - $awayNet)],
            ['factor' => 'Home court', 'edge' => self::HOME_COURT_RATING_BOOST, 'favors' => 'home'],
            ['factor' => 'Home/away splits', 'edge' => round($contextBoost, 1), 'favors' => $this->favorsSide($contextBoost)],
            ['factor' => 'Recent form (L5)', 'edge' => round($formBoost, 1), 'favors' => $this->favorsSide($formBoost)],
        ];

        if ($h2hBoost !== 0.0) {
            $factors[] = ['factor' => 'Head-to-head', 'edge' => round($h2hBoost, 1), 'favors' => $this->favorsSide($h2hBoost)];
        }

        return [
            'predicted_winner' => $predictedWinner,
            'predicted_winner_label' => $predictedWinner === 'home'
                ? ($homeTeam['abbreviation'] ?? 'Home')
                : ($awayTeam['abbreviation'] ?? 'Away'),
            'win_probability' => [
                'home' => $winProbHome,
                'away' => $winProbAway,
            ],
            'projected_score' => [
                'home' => $projectedHomeScore,
                'away' => $projectedAwayScore,
                'total' => round($projectedHomeScore + $projectedAwayScore, 1),
            ],
            'projected_spread' => $projectedSpread,
            'projected_pace' => round($avgPace, 1),
            'confidence' => $confidence,
            'factors' => $factors,
        ];
    }

    private function favorsSide(float $edge): string
    {
        if ($edge > 0.5) {
            return 'home';
        }
        if ($edge < -0.5) {
            return 'away';
        }

        return 'even';
    }

    /**
     * @param  array<string, mixed>  $homeTeam
     * @param  array<string, mixed>  $awayTeam
     * @param  array<string, mixed>  $headToHead
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function generateAnalysis(array $homeTeam, array $awayTeam, array $headToHead, array $prediction): array
    {
        $bullets = [];
        $homeAbbr = $homeTeam['abbreviation'] ?? 'HOME';
        $awayAbbr = $awayTeam['abbreviation'] ?? 'AWAY';

        $homeNet = (float) ($homeTeam['efficiency']['net_rating'] ?? 0);
        $awayNet = (float) ($awayTeam['efficiency']['net_rating'] ?? 0);

        if (abs($homeNet - $awayNet) >= 3) {
            $better = $homeNet > $awayNet ? $homeAbbr : $awayAbbr;
            $bullets[] = "{$better} holds a meaningful efficiency edge (net rating " . round(abs($homeNet - $awayNet), 1) . ').';
        }

        $homeCtx = $homeTeam['context_split'] ?? [];
        $awayCtx = $awayTeam['context_split'] ?? [];
        if (($homeCtx['win_pct'] ?? 0) >= 0.6) {
            $bullets[] = "{$homeAbbr} has been strong at home ({$homeCtx['wins']}-{$homeCtx['losses']}, " . round(($homeCtx['win_pct'] ?? 0) * 100) . '% win rate).';
        }
        if (($awayCtx['win_pct'] ?? 0) <= 0.4 && ($awayCtx['games'] ?? 0) >= 3) {
            $bullets[] = "{$awayAbbr} has struggled on the road ({$awayCtx['wins']}-{$awayCtx['losses']}).";
        }

        $homeDef = $homeTeam['defense']['points_allowed_per_game'] ?? null;
        $awayDef = $awayTeam['defense']['points_allowed_per_game'] ?? null;
        if ($homeDef && $awayDef) {
            $betterDefense = $homeDef < $awayDef ? $homeAbbr : $awayAbbr;
            $bullets[] = "{$betterDefense} has been the better defensive team this season ({$homeAbbr} allows {$homeDef} PPG vs {$awayAbbr} {$awayDef}).";
        }

        if (($headToHead['total_games'] ?? 0) > 0) {
            $bullets[] = "Recent meetings: {$homeAbbr} {$headToHead['home_team_wins']}-{$headToHead['away_team_wins']} vs {$awayAbbr}, averaging {$headToHead['avg_total_points']} total points.";
        }

        $winner = $prediction['predicted_winner_label'] ?? '';
        $conf = $prediction['confidence'] ?? 0;
        $score = $prediction['projected_score'] ?? [];
        $summary = sprintf(
            'Model pick: %s (%s%% win probability). Projected score %s %s – %s %s (total %s).',
            $winner,
            $prediction['predicted_winner'] === 'home'
                ? ($prediction['win_probability']['home'] ?? 50)
                : ($prediction['win_probability']['away'] ?? 50),
            $awayAbbr,
            $score['away'] ?? '–',
            $homeAbbr,
            $score['home'] ?? '–',
            $score['total'] ?? '–'
        );

        if ($conf >= 60) {
            $summary .= " Confidence: {$conf}%.";
        }

        return [
            'summary' => $summary,
            'bullets' => $bullets,
        ];
    }

    /**
     * @param  array<string, mixed>  $homeTeam
     * @param  array<string, mixed>  $awayTeam
     * @return array<string, mixed>
     */
    private function buildComparisonData(array $homeTeam, array $awayTeam): array
    {
        $homeBasic = $homeTeam['season_stats'] ?? [];
        $awayBasic = $awayTeam['season_stats'] ?? [];
        $homeEff = $homeTeam['efficiency'] ?? [];
        $awayEff = $awayTeam['efficiency'] ?? [];

        return [
            'radar' => [
                'labels' => ['Off Rating', 'Def Rating', 'Net Rating', 'PPG', 'Win %', 'Pace'],
                'home' => [
                    (float) ($homeEff['offensive_rating'] ?? 0),
                    120 - (float) ($homeEff['defensive_rating'] ?? 100),
                    (float) ($homeEff['net_rating'] ?? 0) + 20,
                    (float) ($homeBasic['points_per_game'] ?? 0),
                    ((float) ($homeBasic['win_percentage'] ?? 0)) * 100,
                    (float) ($homeTeam['pace']['pace'] ?? 0),
                ],
                'away' => [
                    (float) ($awayEff['offensive_rating'] ?? 0),
                    120 - (float) ($awayEff['defensive_rating'] ?? 100),
                    (float) ($awayEff['net_rating'] ?? 0) + 20,
                    (float) ($awayBasic['points_per_game'] ?? 0),
                    ((float) ($awayBasic['win_percentage'] ?? 0)) * 100,
                    (float) ($awayTeam['pace']['pace'] ?? 0),
                ],
            ],
            'table' => [
                ['stat' => 'Record', 'home' => ($homeBasic['wins'] ?? 0) . '-' . ($homeBasic['losses'] ?? 0), 'away' => ($awayBasic['wins'] ?? 0) . '-' . ($awayBasic['losses'] ?? 0)],
                ['stat' => 'PPG', 'home' => $homeBasic['points_per_game'] ?? 0, 'away' => $awayBasic['points_per_game'] ?? 0],
                ['stat' => 'Opp PPG', 'home' => $homeBasic['points_allowed_per_game'] ?? 0, 'away' => $awayBasic['points_allowed_per_game'] ?? 0],
                ['stat' => 'FG%', 'home' => $homeBasic['field_goal_percentage'] ?? 0, 'away' => $awayBasic['field_goal_percentage'] ?? 0],
                ['stat' => '3P%', 'home' => $homeBasic['three_point_percentage'] ?? 0, 'away' => $awayBasic['three_point_percentage'] ?? 0],
                ['stat' => 'Reb/G', 'home' => $homeBasic['rebounds_per_game'] ?? 0, 'away' => $awayBasic['rebounds_per_game'] ?? 0],
                ['stat' => 'Ast/G', 'home' => $homeBasic['assists_per_game'] ?? 0, 'away' => $awayBasic['assists_per_game'] ?? 0],
                ['stat' => 'TO/G', 'home' => $homeBasic['turnovers_per_game'] ?? 0, 'away' => $awayBasic['turnovers_per_game'] ?? 0],
                ['stat' => 'Off Rating', 'home' => $homeEff['offensive_rating'] ?? 0, 'away' => $awayEff['offensive_rating'] ?? 0],
                ['stat' => 'Def Rating', 'home' => $homeEff['defensive_rating'] ?? 0, 'away' => $awayEff['defensive_rating'] ?? 0],
                ['stat' => 'Net Rating', 'home' => $homeEff['net_rating'] ?? 0, 'away' => $awayEff['net_rating'] ?? 0],
                ['stat' => 'Pace', 'home' => $homeTeam['pace']['pace'] ?? 0, 'away' => $awayTeam['pace']['pace'] ?? 0],
            ],
        ];
    }
}
