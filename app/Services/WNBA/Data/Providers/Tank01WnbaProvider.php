<?php

namespace App\Services\WNBA\Data\Providers;

use App\Contracts\WnbaStatsProvider;
use App\Models\WnbaGame;
use App\Models\WnbaPlayerGame;
use App\Services\RapidApi\RapidApiClient;
use App\Services\RapidApi\Tank01UsageTracker;
use App\Services\WNBA\Data\Mappers\Tank01Mapper;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Tank01WnbaProvider implements WnbaStatsProvider
{
    private Tank01Mapper $mapper;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $cachedTeamsBody = null;

    public function __construct(
        private RapidApiClient $client,
        private Tank01UsageTracker $usageTracker
    ) {
        $this->mapper = new Tank01Mapper((int) config('wnba.seasons.current_season'));
    }

    public function name(): string
    {
        return 'tank01';
    }

    public function fetchTeams(int $season, array $options = []): array
    {
        return $this->mapper->mapTeams($this->fetchTeamsBody());
    }

    public function fetchSchedule(int $season, array $options = []): array
    {
        return $this->mapper->mapScheduleFromTeams($this->fetchTeamsBody());
    }

    public function fetchPlayerBoxscores(int $season, array $options = []): array
    {
        $gameIds = $this->resolveGameIdsForBoxScores($options);
        $records = [];

        foreach ($gameIds as $gameId) {
            if (! $this->usageTracker->canMakeRequest()) {
                Log::warning('Tank01 budget reached during player box score fetch', [
                    'remaining_games' => count($gameIds),
                ]);
                break;
            }

            try {
                $body = $this->client->get(
                    config('tank01.endpoints.box_score'),
                    ['gameID' => $gameId],
                    $this->boxScoreCacheTtl($gameId, $options),
                );
                $mapped = $this->mapper->mapBoxScore($body);
                $records = array_merge($records, $mapped['player']);
            } catch (RuntimeException $e) {
                Log::warning('Tank01 box score fetch failed', [
                    'game_id' => $gameId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $records;
    }

    public function fetchTeamBoxscores(int $season, array $options = []): array
    {
        $gameIds = $this->resolveGameIdsForBoxScores($options);
        $records = [];

        foreach ($gameIds as $gameId) {
            if (! $this->usageTracker->canMakeRequest()) {
                break;
            }

            try {
                $body = $this->client->get(
                    config('tank01.endpoints.box_score'),
                    ['gameID' => $gameId],
                    $this->boxScoreCacheTtl($gameId, $options),
                );
                $mapped = $this->mapper->mapBoxScore($body);
                $records = array_merge($records, $mapped['team']);
            } catch (RuntimeException $e) {
                Log::warning('Tank01 team box score fetch failed', [
                    'game_id' => $gameId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $records;
    }

    public function fetchAvailableSeasons(): array
    {
        return [
            [
                'year' => (int) config('wnba.seasons.current_season'),
                'season' => (string) config('wnba.seasons.current_season_label'),
            ],
        ];
    }

    public function supportsPlayByPlay(): bool
    {
        return false;
    }

    public function supportsIncremental(): bool
    {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPlayerGamelog(string $playerId, int $season, ?int $lastNGames = null): array
    {
        $query = [
            'playerID' => $playerId,
            'season' => (string) $season,
            'fantasyPoints' => 'false',
        ];

        if ($lastNGames !== null) {
            $query['numberOfGames'] = (string) $lastNGames;
        }

        $body = $this->client->get(
            config('tank01.endpoints.games_for_player'),
            $query,
            (int) config('tank01.cache_ttl.games_for_player'),
        );

        return $this->mapper->mapPlayerGamelog(is_array($body) ? $body : [], $playerId, $season);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRosterPlayers(): array
    {
        return $this->mapper->mapRosterPlayers($this->fetchTeamsBody());
    }

    public function pendingBoxScoreGameIds(int $season, array $options = []): array
    {
        return $this->resolveGameIdsForBoxScores($options);
    }

    public function supportsBatchedBoxScoreImport(): bool
    {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchScoreboard(string $gameDate): array
    {
        $body = $this->client->get(
            config('tank01.endpoints.scoreboard'),
            ['gameDate' => $gameDate],
            config('tank01.cache_ttl.scoreboard'),
        );

        return is_array($body) && array_is_list($body) ? $body : [$body];
    }

    /**
     * @return array{player: array<int, array<string, mixed>>, team: array<int, array<string, mixed>>, schedule: array<int, array<string, mixed>>}
     */
    public function syncLiveGames(string $gameDate, int $maxCalls = 5): array
    {
        $calls = 0;
        $player = [];
        $team = [];
        $schedule = [];

        if (! $this->usageTracker->canMakeRequest()) {
            return compact('player', 'team', 'schedule');
        }

        $scoreboard = $this->fetchScoreboard($gameDate);
        $calls++;

        foreach ($scoreboard as $game) {
            if ($calls >= $maxCalls) {
                break;
            }

            $status = (string) ($game['gameStatus'] ?? '');
            if (! $this->mapper->isGameLive($status) && ! $this->mapper->isGameCompleted($status)) {
                continue;
            }

            $gameId = $game['gameID'] ?? null;
            if (! $gameId) {
                continue;
            }

            if (! $this->usageTracker->canMakeRequest()) {
                break;
            }

            try {
                $body = $this->client->get(
                    config('tank01.endpoints.box_score'),
                    ['gameID' => $gameId],
                    $this->mapper->isGameLive($status)
                        ? config('tank01.cache_ttl.box_score_live')
                        : config('tank01.cache_ttl.box_score_final'),
                );
                $calls++;
                $mapped = $this->mapper->mapBoxScore($body);
                $player = array_merge($player, $mapped['player']);
                $team = array_merge($team, $mapped['team']);
                $schedule[] = $this->mapper->mapScheduleGame($gameId, array_merge($game, $body));
            } catch (RuntimeException $e) {
                Log::warning('Tank01 live sync box score failed', ['game_id' => $gameId]);
            }
        }

        return compact('player', 'team', 'schedule');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTeamsBody(): array
    {
        if ($this->cachedTeamsBody !== null) {
            return $this->cachedTeamsBody;
        }

        $body = $this->client->get(
            config('tank01.endpoints.teams'),
            [
                'schedules' => 'true',
                'rosters' => 'true',
                'teamStats' => 'true',
            ],
            config('tank01.cache_ttl.teams_schedule'),
        );

        $this->cachedTeamsBody = is_array($body) && array_is_list($body) ? $body : [$body];

        return $this->cachedTeamsBody;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function resolveGameIdsForBoxScores(array $options): array
    {
        if (! empty($options['game_ids']) && is_array($options['game_ids'])) {
            return array_values($options['game_ids']);
        }

        $schedule = $this->fetchSchedule((int) config('wnba.seasons.current_season'));
        $incremental = $options['incremental'] ?? true;
        $force = $options['force'] ?? false;

        $gameIds = [];
        foreach ($schedule as $game) {
            $gameId = $game['game_id'] ?? null;
            $status = (string) ($game['status_name'] ?? '');

            if (! $gameId) {
                continue;
            }

            if ($this->mapper->isGameLive($status)) {
                $gameIds[] = $gameId;

                continue;
            }

            if (! $this->mapper->isGameCompleted($status) && $status !== 'final') {
                continue;
            }

            if ($incremental && ! $force && $this->gameHasBoxScores($gameId)) {
                continue;
            }

            $gameIds[] = $gameId;
        }

        if (! $force && ! empty($gameIds)) {
            $cost = $this->usageTracker->estimateCost('box_score', count($gameIds));
            if (! $this->usageTracker->hasBudgetFor('box_score', count($gameIds))) {
                Log::warning('Tank01 insufficient budget for box score batch', [
                    'games_requested' => count($gameIds),
                    'estimated_cost' => $cost,
                    'remaining' => max(0, $this->usageTracker->getMonthlyLimit() - $this->usageTracker->getMonthlyRequests()),
                ]);

                throw new RuntimeException(
                    'Insufficient Tank01 API budget for '.count($gameIds).' box score calls. '
                    .'Use --incremental (default) or bootstrap from SportsDataverse.'
                );
            }
        }

        return $gameIds;
    }

    private function gameHasBoxScores(string $gameId): bool
    {
        $game = WnbaGame::where('game_id', $gameId)->first();
        if (! $game) {
            return false;
        }

        return WnbaPlayerGame::where('game_id', $game->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function boxScoreCacheTtl(string $gameId, array $options): int
    {
        if (! empty($options['live'])) {
            return (int) config('tank01.cache_ttl.box_score_live');
        }

        return (int) config('tank01.cache_ttl.box_score_final');
    }
}
