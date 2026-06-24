<?php

namespace App\Services\WNBA\Data\Providers;

use App\Contracts\WnbaStatsProvider;
use App\Models\WnbaGame;
use App\Models\WnbaPlayerGame;
use App\Services\WNBA\Data\Mappers\EspnMapper;
use App\Services\WNBA\Data\Support\EspnApiClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EspnWnbaProvider implements WnbaStatsProvider
{
    private EspnMapper $mapper;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $cachedSchedule = null;

    public function __construct(
        private EspnApiClient $client
    ) {
        $this->mapper = new EspnMapper((int) config('wnba.seasons.current_season'));
    }

    public function name(): string
    {
        return 'espn';
    }

    public function fetchTeams(int $season, array $options = []): array
    {
        return $this->mapper->mapTeams($this->client->teams());
    }

    public function fetchSchedule(int $season, array $options = []): array
    {
        return $this->scheduleRecords($season);
    }

    public function fetchPlayerBoxscores(int $season, array $options = []): array
    {
        $records = [];

        foreach ($this->resolveGameIdsForBoxScores($season, $options) as $gameId) {
            try {
                $summary = $this->client->summary($gameId, ! empty($options['live']));
                $mapped = $this->mapper->mapSummary($summary);
                $records = array_merge($records, $mapped['player']);
            } catch (RuntimeException $e) {
                Log::warning('ESPN box score fetch failed', [
                    'game_id' => $gameId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $records;
    }

    public function fetchTeamBoxscores(int $season, array $options = []): array
    {
        $records = [];

        foreach ($this->resolveGameIdsForBoxScores($season, $options) as $gameId) {
            try {
                $summary = $this->client->summary($gameId, ! empty($options['live']));
                $mapped = $this->mapper->mapSummary($summary);
                $records = array_merge($records, $mapped['team']);
            } catch (RuntimeException $e) {
                Log::warning('ESPN team box score fetch failed', [
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
            ['year' => $this->seasonYear() - 2, 'season' => (string) ($this->seasonYear() - 2)],
            ['year' => $this->seasonYear() - 1, 'season' => (string) ($this->seasonYear() - 1)],
            ['year' => $this->seasonYear(), 'season' => (string) config('wnba.seasons.current_season_label', $this->seasonYear())],
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

    public function pendingBoxScoreGameIds(int $season, array $options = []): array
    {
        return $this->resolveGameIdsForBoxScores($season, $options);
    }

    public function supportsBatchedBoxScoreImport(): bool
    {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPlayerGamelog(string $athleteId, int $season): array
    {
        $payload = $this->client->athleteGamelog($athleteId, $season);

        return $this->mapper->mapPlayerGamelog($athleteId, $payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRosterPlayers(string $teamId): array
    {
        $payload = $this->client->teamRoster($teamId);
        $records = [];

        foreach ($payload['athletes'] ?? [] as $athlete) {
            $records[] = [
                'athlete_id' => $athlete['id'] ?? null,
                'athlete_display_name' => $athlete['displayName'] ?? null,
                'athlete_short_name' => $athlete['shortName'] ?? null,
                'athlete_jersey' => $athlete['jersey'] ?? null,
                'athlete_headshot_href' => $athlete['headshot']['href'] ?? null,
                'athlete_position_name' => $athlete['position']['displayName'] ?? null,
                'athlete_position_abbreviation' => $athlete['position']['abbreviation'] ?? null,
                'team_id' => $payload['team']['id'] ?? $teamId,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduleRecords(int $season): array
    {
        if ($this->cachedSchedule !== null) {
            return $this->cachedSchedule;
        }

        $teamsPayload = $this->client->teams();
        $events = [];

        foreach ($this->mapper->teamIds($teamsPayload) as $teamId) {
            try {
                $schedule = $this->client->teamSchedule($teamId, $season);
                foreach ($schedule['events'] ?? [] as $event) {
                    $events[] = $event;
                }
            } catch (RuntimeException $e) {
                Log::warning('ESPN team schedule fetch failed', [
                    'team_id' => $teamId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->cachedSchedule = $this->mapper->mapSchedule($events);

        return $this->cachedSchedule;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function resolveGameIdsForBoxScores(int $season, array $options): array
    {
        if (! empty($options['game_ids']) && is_array($options['game_ids'])) {
            return array_values(array_map('strval', $options['game_ids']));
        }

        $incremental = $options['incremental'] ?? true;
        $force = $options['force'] ?? false;
        $gameIds = [];

        foreach ($this->scheduleRecords($season) as $game) {
            $gameId = (string) ($game['game_id'] ?? '');
            if ($gameId === '') {
                continue;
            }

            $statusName = (string) ($game['status_name'] ?? '');
            $statusType = (string) ($game['status_type'] ?? '');

            if ($this->mapper->isGameLive($statusName, $statusType)) {
                $gameIds[] = $gameId;

                continue;
            }

            if (! $this->mapper->isGameCompleted($statusName, $statusType)) {
                continue;
            }

            if ($incremental && ! $force && $this->gameHasBoxScores($gameId)) {
                continue;
            }

            $gameIds[] = $gameId;
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

    private function seasonYear(): int
    {
        return (int) config('wnba.seasons.current_season');
    }
}
