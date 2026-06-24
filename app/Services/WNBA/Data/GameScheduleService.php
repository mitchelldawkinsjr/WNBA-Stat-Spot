<?php

namespace App\Services\WNBA\Data;

use App\Models\WnbaGame;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use Illuminate\Support\Facades\Cache;

class GameScheduleService
{
    public function list(int $season, bool $includeLive = true): array
    {
        $cacheKey = "games_list_{$season}_".($includeLive ? 'live' : 'db');

        return Cache::remember($cacheKey, 300, function () use ($season, $includeLive) {
            $dbGames = WnbaGame::with(['gameTeams.team', 'gameTeams.opponentTeam'])
                ->where('season', $season)
                ->orderByDesc('game_date')
                ->get();

            $dbByGameId = $dbGames->keyBy('game_id');
            $results = [];

            if ($includeLive) {
                $liveSchedule = app(EspnWnbaProvider::class)->fetchSchedule($season);

                foreach ($liveSchedule as $record) {
                    $gameId = (string) ($record['game_id'] ?? '');
                    if ($gameId === '') {
                        continue;
                    }

                    if ($dbByGameId->has($gameId)) {
                        $results[] = $this->transformDbGame($dbByGameId->get($gameId), $record);
                        $dbByGameId->forget($gameId);
                    } else {
                        $results[] = $this->transformScheduleRecord($record);
                    }
                }
            } else {
                foreach ($dbGames as $game) {
                    $results[] = $this->transformDbGame($game);
                }

                return $results;
            }

            foreach ($dbByGameId as $game) {
                $results[] = $this->transformDbGame($game);
            }

            usort($results, fn (array $a, array $b) => strcmp((string) ($b['game_date'] ?? ''), (string) ($a['game_date'] ?? '')));

            return $results;
        });
    }

    private function transformDbGame(WnbaGame $game, ?array $scheduleRecord = null): array
    {
        $result = [
            'id' => $game->id,
            'game_id' => $game->game_id,
            'season' => (string) $game->season,
            'season_type' => $this->seasonTypeName($game->season_type),
            'game_date' => $game->game_date?->format('Y-m-d'),
            'game_date_time' => $game->game_date_time?->toIso8601String(),
            'venue_name' => $game->venue_name,
            'venue_city' => $game->venue_city,
            'venue_state' => $game->venue_state,
            'status_name' => $game->status_name,
            'created_at' => $game->created_at,
            'updated_at' => $game->updated_at,
            'home_team' => $this->teamInfoFromDb($game, 'home'),
            'away_team' => $this->teamInfoFromDb($game, 'away'),
            'final_score' => $this->finalScoreFromDb($game),
            'source' => 'database',
        ];

        if ($scheduleRecord !== null) {
            $result = $this->enrichFromSchedule($result, $scheduleRecord);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $dbGame
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function enrichFromSchedule(array $dbGame, array $record): array
    {
        $espn = $this->transformScheduleRecord($record);

        if ($dbGame['home_team'] === null) {
            $dbGame['home_team'] = $espn['home_team'];
        }

        if ($dbGame['away_team'] === null) {
            $dbGame['away_team'] = $espn['away_team'];
        }

        foreach (['venue_name', 'venue_city', 'venue_state', 'status_name', 'game_date', 'game_date_time'] as $field) {
            if (empty($dbGame[$field]) && ! empty($espn[$field])) {
                $dbGame[$field] = $espn[$field];
            }
        }

        $dbGame['home_team'] = $this->mergeTeamScores($dbGame['home_team'], $espn['home_team']);
        $dbGame['away_team'] = $this->mergeTeamScores($dbGame['away_team'], $espn['away_team']);

        if ($dbGame['final_score'] === null && $espn['final_score'] !== null) {
            $dbGame['final_score'] = $espn['final_score'];
        }

        return $dbGame;
    }

    /**
     * @param  array<string, mixed>|null  $dbTeam
     * @param  array<string, mixed>|null  $espnTeam
     * @return array<string, mixed>|null
     */
    private function mergeTeamScores(?array $dbTeam, ?array $espnTeam): ?array
    {
        if ($dbTeam === null) {
            return $espnTeam;
        }

        if ($espnTeam === null) {
            return $dbTeam;
        }

        if (($dbTeam['score'] === null || $dbTeam['score'] === 0) && $espnTeam['score'] !== null) {
            $dbTeam['score'] = $espnTeam['score'];
            $dbTeam['winner'] = $espnTeam['winner'];
        }

        foreach (['name', 'abbreviation', 'logo'] as $field) {
            if (empty($dbTeam[$field]) && ! empty($espnTeam[$field])) {
                $dbTeam[$field] = $espnTeam[$field];
            }
        }

        return $dbTeam;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function transformScheduleRecord(array $record): array
    {
        $statusName = $record['status_name'] ?? null;
        $isFinal = $statusName === 'STATUS_FINAL';

        return [
            'id' => 0,
            'game_id' => (string) ($record['game_id'] ?? ''),
            'season' => (string) ($record['season'] ?? ''),
            'season_type' => (string) ($record['season_type'] ?? 'Regular Season'),
            'game_date' => $record['game_date'] ?? null,
            'game_date_time' => $record['game_date_time'] ?? null,
            'venue_name' => $record['venue_name'] ?? null,
            'venue_city' => $record['venue_city'] ?? null,
            'venue_state' => $record['venue_state'] ?? null,
            'status_name' => $statusName,
            'created_at' => null,
            'updated_at' => null,
            'home_team' => [
                'id' => 0,
                'team_id' => (string) ($record['home_team_id'] ?? ''),
                'name' => $record['home_team_display_name'] ?? $record['home_team_name'] ?? null,
                'abbreviation' => $record['home_team_abbreviation'] ?? null,
                'logo' => $record['home_team_logo'] ?? null,
                'score' => $record['home_team_score'] ?? null,
                'winner' => (bool) ($record['home_team_winner'] ?? false),
            ],
            'away_team' => [
                'id' => 0,
                'team_id' => (string) ($record['away_team_id'] ?? ''),
                'name' => $record['away_team_display_name'] ?? $record['away_team_name'] ?? null,
                'abbreviation' => $record['away_team_abbreviation'] ?? null,
                'logo' => $record['away_team_logo'] ?? null,
                'score' => $record['away_team_score'] ?? null,
                'winner' => (bool) ($record['away_team_winner'] ?? false),
            ],
            'final_score' => $isFinal ? [
                'home' => $record['home_team_score'] ?? null,
                'away' => $record['away_team_score'] ?? null,
                'final' => true,
            ] : null,
            'source' => 'espn',
        ];
    }

    private function teamInfoFromDb(WnbaGame $game, string $homeAway): ?array
    {
        $gameTeam = $game->gameTeams->where('home_away', $homeAway)->first();

        if (! $gameTeam?->team) {
            return null;
        }

        return [
            'id' => $gameTeam->team->id,
            'team_id' => $gameTeam->team->team_id,
            'name' => $gameTeam->team->team_display_name,
            'abbreviation' => $gameTeam->team->team_abbreviation,
            'logo' => $gameTeam->team->team_logo,
            'score' => $gameTeam->team_score,
            'winner' => $gameTeam->team_winner,
        ];
    }

    private function finalScoreFromDb(WnbaGame $game): ?array
    {
        $homeTeam = $game->gameTeams->where('home_away', 'home')->first();
        $awayTeam = $game->gameTeams->where('home_away', 'away')->first();

        if (! $homeTeam || ! $awayTeam) {
            return null;
        }

        return [
            'home' => $homeTeam->team_score,
            'away' => $awayTeam->team_score,
            'final' => $game->status_name === 'STATUS_FINAL',
        ];
    }

    private function seasonTypeName(mixed $seasonType): string
    {
        return match ((int) $seasonType) {
            1 => 'Preseason',
            2 => 'Regular Season',
            3 => 'Playoffs',
            4 => 'Finals',
            default => is_string($seasonType) && $seasonType !== '' ? $seasonType : 'Regular Season',
        };
    }
}
