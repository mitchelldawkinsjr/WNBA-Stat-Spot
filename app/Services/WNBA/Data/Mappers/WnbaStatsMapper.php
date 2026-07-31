<?php

namespace App\Services\WNBA\Data\Mappers;

use App\Services\WNBA\Data\Support\TeamCatalog;

/**
 * Normalizes stats.wnba.com / CDN payloads into the same field names used by
 * EspnMapper and the canonical wnba_player_games / wnba_game_teams columns.
 */
class WnbaStatsMapper
{
    /**
     * Flatten leagueSchedule.gameDates[].games[] into comparable rows.
     *
     * @param  array<string, mixed>  $schedulePayload
     * @return list<array{
     *   game_id: string,
     *   game_date: string,
     *   home_tricode: string,
     *   away_tricode: string,
     *   home_team_id: string|null,
     *   away_team_id: string|null,
     *   status_text: string|null
     * }>
     */
    public function mapSchedule(array $schedulePayload): array
    {
        $dates = $schedulePayload['leagueSchedule']['gameDates']
            ?? $schedulePayload['gameDates']
            ?? [];

        $rows = [];
        foreach ($dates as $dateBlock) {
            if (! is_array($dateBlock)) {
                continue;
            }

            foreach ($dateBlock['games'] ?? [] as $game) {
                if (! is_array($game)) {
                    continue;
                }

                $gameId = (string) ($game['gameId'] ?? '');
                if ($gameId === '') {
                    continue;
                }

                $home = is_array($game['homeTeam'] ?? null) ? $game['homeTeam'] : [];
                $away = is_array($game['awayTeam'] ?? null) ? $game['awayTeam'] : [];

                $gameDate = $this->normalizeScheduleDate(
                    (string) ($game['gameDateEst'] ?? $dateBlock['gameDate'] ?? '')
                );
                if ($gameDate === null) {
                    continue;
                }

                $rows[] = [
                    'game_id' => $gameId,
                    'game_date' => $gameDate,
                    'home_tricode' => TeamCatalog::canonicalAbbreviation((string) ($home['teamTricode'] ?? '')),
                    'away_tricode' => TeamCatalog::canonicalAbbreviation((string) ($away['teamTricode'] ?? '')),
                    'home_team_id' => isset($home['teamId']) ? (string) $home['teamId'] : null,
                    'away_team_id' => isset($away['teamId']) ? (string) $away['teamId'] : null,
                    'status_text' => isset($game['gameStatusText']) ? (string) $game['gameStatusText'] : null,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $boxPayload
     * @return array{
     *   game_id: string|null,
     *   players: list<array<string, mixed>>,
     *   teams: list<array<string, mixed>>
     * }
     */
    public function mapBoxScoreTraditional(array $boxPayload): array
    {
        $box = $boxPayload['boxScoreTraditional'] ?? $boxPayload;
        if (! is_array($box)) {
            return ['game_id' => null, 'players' => [], 'teams' => []];
        }

        $gameId = isset($box['gameId']) ? (string) $box['gameId'] : null;
        $players = [];
        $teams = [];

        foreach (['home' => 'homeTeam', 'away' => 'awayTeam'] as $side => $key) {
            $team = is_array($box[$key] ?? null) ? $box[$key] : null;
            if ($team === null) {
                continue;
            }

            $teamId = isset($team['teamId']) ? (string) $team['teamId'] : null;
            $tricode = TeamCatalog::canonicalAbbreviation((string) ($team['teamTricode'] ?? ''));
            $stats = is_array($team['statistics'] ?? null) ? $team['statistics'] : [];

            $teams[] = array_merge([
                'wnba_stats_team_id' => $teamId,
                'team_tricode' => $tricode,
                'home_away' => $side,
                'team_score' => $this->toInt($stats['points'] ?? 0),
            ], $this->mapCountingStats($stats));

            foreach ($team['players'] ?? [] as $player) {
                if (! is_array($player)) {
                    continue;
                }

                $playerStats = is_array($player['statistics'] ?? null) ? $player['statistics'] : [];
                $comment = trim((string) ($player['comment'] ?? ''));
                $didNotPlay = $comment !== '';

                $players[] = array_merge([
                    'wnba_stats_player_id' => isset($player['personId']) ? (string) $player['personId'] : null,
                    'wnba_stats_team_id' => $teamId,
                    'team_tricode' => $tricode,
                    'home_away' => $side,
                    'first_name' => (string) ($player['firstName'] ?? ''),
                    'family_name' => (string) ($player['familyName'] ?? ''),
                    'jersey_num' => isset($player['jerseyNum']) ? (string) $player['jerseyNum'] : null,
                    'comment' => $comment !== '' ? $comment : null,
                    'did_not_play' => $didNotPlay,
                    'minutes' => $this->formatMinutes($playerStats['minutes'] ?? null),
                ], $this->mapCountingStats($playerStats));
            }
        }

        return [
            'game_id' => $gameId,
            'players' => $players,
            'teams' => $teams,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, int>
     */
    private function mapCountingStats(array $stats): array
    {
        return [
            'points' => $this->toInt($stats['points'] ?? 0),
            'field_goals_made' => $this->toInt($stats['fieldGoalsMade'] ?? 0),
            'field_goals_attempted' => $this->toInt($stats['fieldGoalsAttempted'] ?? 0),
            'three_point_field_goals_made' => $this->toInt($stats['threePointersMade'] ?? 0),
            'three_point_field_goals_attempted' => $this->toInt($stats['threePointersAttempted'] ?? 0),
            'free_throws_made' => $this->toInt($stats['freeThrowsMade'] ?? 0),
            'free_throws_attempted' => $this->toInt($stats['freeThrowsAttempted'] ?? 0),
            'offensive_rebounds' => $this->toInt($stats['reboundsOffensive'] ?? 0),
            'defensive_rebounds' => $this->toInt($stats['reboundsDefensive'] ?? 0),
            'rebounds' => $this->toInt($stats['reboundsTotal'] ?? 0),
            'assists' => $this->toInt($stats['assists'] ?? 0),
            'steals' => $this->toInt($stats['steals'] ?? 0),
            'blocks' => $this->toInt($stats['blocks'] ?? 0),
            'turnovers' => $this->toInt($stats['turnovers'] ?? 0),
            'fouls' => $this->toInt($stats['foulsPersonal'] ?? 0),
            'plus_minus' => $this->toInt($stats['plusMinusPoints'] ?? 0),
        ];
    }

    private function normalizeScheduleDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // CDN uses "MM/DD/YYYY 00:00:00" or ISO-ish timestamps.
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[1], (int) $m[2]);
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function formatMinutes(mixed $minutes): ?string
    {
        $decimal = $this->toFloatMinutes($minutes);
        if ($decimal === null) {
            return null;
        }

        $mins = (int) floor($decimal);
        $secs = (int) round(($decimal - $mins) * 60);

        return sprintf('%d:%02d', $mins, $secs);
    }

    public function toFloatMinutes(mixed $minutes): ?float
    {
        if ($minutes === null || $minutes === '') {
            return null;
        }

        if (is_numeric($minutes)) {
            return (float) $minutes;
        }

        if (! is_string($minutes)) {
            return null;
        }

        // ISO-8601 duration from V3: PT32M15.00S
        if (preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?$/i', $minutes, $m)) {
            $hours = (float) ($m[1] ?? 0);
            $mins = (float) ($m[2] ?? 0);
            $secs = (float) ($m[3] ?? 0);

            return round($hours * 60 + $mins + $secs / 60, 2);
        }

        if (str_contains($minutes, ':')) {
            [$mins, $secs] = array_pad(explode(':', $minutes, 2), 2, '0');
            if (is_numeric($mins) && is_numeric($secs)) {
                return round((float) $mins + ((float) $secs) / 60, 2);
            }
        }

        return null;
    }

    private function toInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return 0;
    }
}
