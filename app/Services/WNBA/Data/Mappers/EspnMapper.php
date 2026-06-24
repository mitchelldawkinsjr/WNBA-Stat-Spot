<?php

namespace App\Services\WNBA\Data\Mappers;

class EspnMapper
{
    public function __construct(
        private int $seasonYear
    ) {}

    /**
     * @param  array<string, mixed>  $teamsPayload
     * @return array<int, array<string, mixed>>
     */
    public function mapTeams(array $teamsPayload): array
    {
        $records = [];

        foreach ($this->extractTeams($teamsPayload) as $team) {
            $records[] = [
                'team_id' => $team['id'] ?? null,
                'team_name' => $team['name'] ?? null,
                'team_location' => $team['location'] ?? null,
                'team_abbreviation' => $team['abbreviation'] ?? null,
                'team_display_name' => $team['displayName'] ?? null,
                'team_uid' => $team['uid'] ?? null,
                'team_slug' => $team['slug'] ?? null,
                'team_logo' => $team['logo'] ?? ($team['logos'][0]['href'] ?? null),
                'team_color' => $team['color'] ?? null,
                'team_alternate_color' => $team['alternateColor'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param  array<int, array<string, mixed>>  $scheduleEvents
     * @return array<int, array<string, mixed>>
     */
    public function mapSchedule(array $scheduleEvents): array
    {
        $records = [];
        $seen = [];

        foreach ($scheduleEvents as $event) {
            $gameId = (string) ($event['id'] ?? '');
            if ($gameId === '' || isset($seen[$gameId])) {
                continue;
            }
            $seen[$gameId] = true;

            $competition = $event['competitions'][0] ?? [];
            $competitors = $competition['competitors'] ?? [];
            $home = $this->findCompetitor($competitors, 'home');
            $away = $this->findCompetitor($competitors, 'away');
            $status = $competition['status']['type'] ?? [];
            $venue = $competition['venue'] ?? [];
            $seasonType = $event['seasonType'] ?? [];

            $records[] = [
                'game_id' => $gameId,
                'season' => $event['season']['year'] ?? $this->seasonYear,
                'season_type' => $seasonType['name'] ?? $seasonType['abbreviation'] ?? null,
                'game_date' => isset($event['date']) ? substr((string) $event['date'], 0, 10) : null,
                'game_date_time' => $event['date'] ?? null,
                'home_team_id' => $home['team']['id'] ?? null,
                'home_team_name' => $home['team']['name'] ?? null,
                'home_team_location' => $home['team']['location'] ?? null,
                'home_team_abbreviation' => $home['team']['abbreviation'] ?? null,
                'home_team_display_name' => $home['team']['displayName'] ?? null,
                'home_team_score' => isset($home['score']['value']) ? (int) $home['score']['value'] : null,
                'home_team_winner' => (bool) ($home['winner'] ?? false),
                'home_team_logo' => $home['team']['logo'] ?? ($home['team']['logos'][0]['href'] ?? null),
                'away_team_id' => $away['team']['id'] ?? null,
                'away_team_name' => $away['team']['name'] ?? null,
                'away_team_location' => $away['team']['location'] ?? null,
                'away_team_abbreviation' => $away['team']['abbreviation'] ?? null,
                'away_team_display_name' => $away['team']['displayName'] ?? null,
                'away_team_score' => isset($away['score']['value']) ? (int) $away['score']['value'] : null,
                'away_team_winner' => (bool) ($away['winner'] ?? false),
                'away_team_logo' => $away['team']['logo'] ?? ($away['team']['logos'][0]['href'] ?? null),
                'venue_name' => $venue['fullName'] ?? null,
                'venue_city' => $venue['address']['city'] ?? null,
                'venue_state' => $venue['address']['state'] ?? null,
                'venue_country' => $venue['address']['country'] ?? null,
                'status_name' => $status['name'] ?? null,
                'status_type' => $status['state'] ?? null,
                'status_abbreviation' => $status['shortDetail'] ?? $status['description'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array{player: array<int, array<string, mixed>>, team: array<int, array<string, mixed>>}
     */
    public function mapSummary(array $summary): array
    {
        $header = $summary['header']['competitions'][0] ?? [];
        $gameId = (string) ($header['id'] ?? ($summary['header']['id'] ?? ''));
        $gameDate = isset($header['date']) ? substr((string) $header['date'], 0, 10) : null;
        $gameDateTime = $header['date'] ?? null;
        $season = $header['season']['year'] ?? $this->seasonYear;
        $seasonType = $header['season']['slug'] ?? $header['season']['type'] ?? null;

        $competitors = $header['competitors'] ?? [];
        $home = $this->findCompetitor($competitors, 'home');
        $away = $this->findCompetitor($competitors, 'away');

        $playerRecords = [];
        foreach ($summary['boxscore']['players'] ?? [] as $teamBlock) {
            $team = $teamBlock['team'] ?? [];
            $side = $this->resolveSide($team['id'] ?? null, $home, $away);
            if ($side === null) {
                continue;
            }

            $own = $side === 'home' ? $home : $away;
            $opp = $side === 'home' ? $away : $home;
            $teamScore = (int) ($own['score'] ?? 0);
            $opponentScore = (int) ($opp['score'] ?? 0);

            foreach ($teamBlock['statistics'] ?? [] as $statGroup) {
                $keys = $statGroup['keys'] ?? $statGroup['names'] ?? [];
                foreach ($statGroup['athletes'] ?? [] as $row) {
                    $mapped = $this->mapAthleteStats($keys, $row['stats'] ?? []);
                    if ($row['didNotPlay'] ?? false) {
                        $mapped['did_not_play'] = true;
                        $mapped['reason'] = $row['reason'] ?? 'DID NOT PLAY';
                    }

                    $playerRecords[] = $this->playerBoxRow(
                        $gameId,
                        $season,
                        $seasonType,
                        $gameDate,
                        $gameDateTime,
                        $side,
                        $team,
                        $own,
                        $opp,
                        $teamScore,
                        $opponentScore,
                        $row['athlete'] ?? [],
                        $mapped,
                        (bool) ($row['starter'] ?? false),
                        (bool) ($row['active'] ?? true),
                        (bool) ($row['ejected'] ?? false),
                        (bool) ($row['didNotPlay'] ?? false),
                        $row['reason'] ?? null,
                    );
                }
            }
        }

        $teamRecords = [];
        foreach ($summary['boxscore']['teams'] ?? [] as $teamBlock) {
            $team = $teamBlock['team'] ?? [];
            $side = $this->resolveSide($team['id'] ?? null, $home, $away);
            if ($side === null) {
                continue;
            }

            $own = $side === 'home' ? $home : $away;
            $opp = $side === 'home' ? $away : $home;
            $stats = $this->mapTeamStatistics($teamBlock['statistics'] ?? []);

            $teamRecords[] = [
                'game_id' => $gameId,
                'season' => $season,
                'season_type' => $seasonType,
                'game_date' => $gameDate,
                'game_date_time' => $gameDateTime,
                'team_id' => $team['id'] ?? null,
                'team_name' => $team['name'] ?? null,
                'team_location' => $team['location'] ?? null,
                'team_abbreviation' => $team['abbreviation'] ?? null,
                'team_display_name' => $team['displayName'] ?? null,
                'team_uid' => $team['uid'] ?? null,
                'team_slug' => $team['slug'] ?? null,
                'team_logo' => $team['logo'] ?? null,
                'team_color' => $team['color'] ?? null,
                'team_alternate_color' => $team['alternateColor'] ?? null,
                'home_away' => $side,
                'team_winner' => ($own['winner'] ?? false) === true,
                'team_score' => (int) ($own['score'] ?? 0),
                'opponent_team_id' => $opp['team']['id'] ?? null,
                'opponent_team_name' => $opp['team']['name'] ?? null,
                'opponent_team_location' => $opp['team']['location'] ?? null,
                'opponent_team_display_name' => $opp['team']['displayName'] ?? null,
                'opponent_team_abbreviation' => $opp['team']['abbreviation'] ?? null,
                'opponent_team_uid' => $opp['team']['uid'] ?? null,
                'opponent_team_slug' => $opp['team']['slug'] ?? null,
                'opponent_team_logo' => $opp['team']['logo'] ?? null,
                'opponent_team_color' => $opp['team']['color'] ?? null,
                'opponent_team_alternate_color' => $opp['team']['alternateColor'] ?? null,
                'opponent_team_score' => (int) ($opp['score'] ?? 0),
                ...$stats,
            ];
        }

        return [
            'player' => $playerRecords,
            'team' => $teamRecords,
        ];
    }

    /**
     * @param  array<string, mixed>  $gamelogPayload
     * @return array<int, array<string, mixed>>
     */
    public function mapPlayerGamelog(string $athleteId, array $gamelogPayload): array
    {
        $labels = $gamelogPayload['labels'] ?? [];
        $names = $gamelogPayload['names'] ?? [];
        $events = $gamelogPayload['events'] ?? [];
        $rows = [];

        foreach ($gamelogPayload['seasonTypes'] ?? [] as $seasonType) {
            foreach ($seasonType['categories'] ?? [] as $category) {
                foreach ($category['events'] ?? [] as $gameId => $stats) {
                    if (! is_array($stats)) {
                        continue;
                    }

                    $eventId = (string) ($stats['eventId'] ?? $gameId);
                    $statValues = $stats['stats'] ?? $stats;
                    if (! is_array($statValues)) {
                        continue;
                    }

                    $meta = $events[$eventId] ?? [];
                    $mapped = $this->mapGamelogStats($labels, $names, $statValues);

                    $rows[] = [
                        'game_id' => $eventId,
                        'athlete_id' => $athleteId,
                        'game_date' => isset($meta['gameDate']) ? substr((string) $meta['gameDate'], 0, 10) : null,
                        'game_date_time' => $meta['gameDate'] ?? null,
                        'home_away' => ($meta['atVs'] ?? '') === '@' ? 'away' : 'home',
                        'game_result' => $meta['gameResult'] ?? null,
                        'score' => $meta['score'] ?? null,
                        'opponent_team_id' => $meta['opponent']['id'] ?? null,
                        'opponent_team_name' => $meta['opponent']['displayName'] ?? null,
                        'opponent_team_abbreviation' => $meta['opponent']['abbreviation'] ?? null,
                        'season_type' => $seasonType['displayName'] ?? $seasonType['name'] ?? null,
                        ...$mapped,
                    ];
                }
            }
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) ($b['game_date'] ?? ''), (string) ($a['game_date'] ?? '')));

        return $rows;
    }

    public function isGameCompleted(?string $statusName, ?string $statusType): bool
    {
        $name = strtoupper((string) $statusName);
        $type = strtolower((string) $statusType);

        return str_contains($name, 'FINAL') || $type === 'post';
    }

    public function isGameLive(?string $statusName, ?string $statusType): bool
    {
        $name = strtoupper((string) $statusName);
        $type = strtolower((string) $statusType);

        return str_contains($name, 'IN_PROGRESS') || str_contains($name, 'HALFTIME') || $type === 'in';
    }

    /**
     * @param  array<string, mixed>  $teamsPayload
     * @return array<int, string>
     */
    public function teamIds(array $teamsPayload): array
    {
        return array_values(array_filter(array_map(
            fn (array $team) => (string) ($team['id'] ?? ''),
            $this->extractTeams($teamsPayload),
        )));
    }

    /**
     * @param  array<string, mixed>  $teamsPayload
     * @return array<int, array<string, mixed>>
     */
    private function extractTeams(array $teamsPayload): array
    {
        $teams = [];
        foreach ($teamsPayload['sports'][0]['leagues'][0]['teams'] ?? [] as $entry) {
            if (isset($entry['team']) && is_array($entry['team'])) {
                $teams[] = $entry['team'];
            }
        }

        return $teams;
    }

    /**
     * @param  array<int, array<string, mixed>>  $competitors
     * @return array<string, mixed>
     */
    private function findCompetitor(array $competitors, string $homeAway): array
    {
        foreach ($competitors as $competitor) {
            if (($competitor['homeAway'] ?? '') === $homeAway) {
                return $competitor;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $home
     * @param  array<string, mixed>  $away
     */
    private function resolveSide(?string $teamId, array $home, array $away): ?string
    {
        if ($teamId === null) {
            return null;
        }

        if ((string) ($home['team']['id'] ?? '') === (string) $teamId) {
            return 'home';
        }

        if ((string) ($away['team']['id'] ?? '') === (string) $teamId) {
            return 'away';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string>  $values
     * @return array<string, mixed>
     */
    private function mapAthleteStats(array $keys, array $values): array
    {
        $stats = [];
        foreach ($keys as $index => $key) {
            $stats[$key] = $values[$index] ?? null;
        }

        [$fgm, $fga] = $this->parseMadeAttempted($stats['fieldGoalsMade-fieldGoalsAttempted'] ?? ($stats['FG'] ?? '0-0'));
        [$tpm, $tpa] = $this->parseMadeAttempted($stats['threePointFieldGoalsMade-threePointFieldGoalsAttempted'] ?? ($stats['3PT'] ?? '0-0'));
        [$ftm, $fta] = $this->parseMadeAttempted($stats['freeThrowsMade-freeThrowsAttempted'] ?? ($stats['FT'] ?? '0-0'));

        return [
            'minutes' => $this->toFloat($stats['minutes'] ?? ($stats['MIN'] ?? 0)),
            'points' => $this->toInt($stats['points'] ?? ($stats['PTS'] ?? 0)),
            'field_goals_made' => $fgm,
            'field_goals_attempted' => $fga,
            'three_point_field_goals_made' => $tpm,
            'three_point_field_goals_attempted' => $tpa,
            'free_throws_made' => $ftm,
            'free_throws_attempted' => $fta,
            'rebounds' => $this->toInt($stats['rebounds'] ?? ($stats['REB'] ?? 0)),
            'offensive_rebounds' => $this->toInt($stats['offensiveRebounds'] ?? ($stats['OREB'] ?? 0)),
            'defensive_rebounds' => $this->toInt($stats['defensiveRebounds'] ?? ($stats['DREB'] ?? 0)),
            'assists' => $this->toInt($stats['assists'] ?? ($stats['AST'] ?? 0)),
            'steals' => $this->toInt($stats['steals'] ?? ($stats['STL'] ?? 0)),
            'blocks' => $this->toInt($stats['blocks'] ?? ($stats['BLK'] ?? 0)),
            'turnovers' => $this->toInt($stats['turnovers'] ?? ($stats['TO'] ?? 0)),
            'fouls' => $this->toInt($stats['fouls'] ?? ($stats['PF'] ?? 0)),
            'plus_minus' => $this->toInt($stats['plusMinus'] ?? ($stats['+/-'] ?? 0)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $statistics
     * @return array<string, int>
     */
    private function mapTeamStatistics(array $statistics): array
    {
        $byName = [];
        foreach ($statistics as $stat) {
            if (! empty($stat['name'])) {
                $byName[$stat['name']] = $stat['displayValue'] ?? '0';
            }
        }

        [$fgm, $fga] = $this->parseMadeAttempted($byName['fieldGoalsMade-fieldGoalsAttempted'] ?? '0-0');
        [$tpm, $tpa] = $this->parseMadeAttempted($byName['threePointFieldGoalsMade-threePointFieldGoalsAttempted'] ?? '0-0');
        [$ftm, $fta] = $this->parseMadeAttempted($byName['freeThrowsMade-freeThrowsAttempted'] ?? '0-0');

        return [
            'field_goals_made' => $fgm,
            'field_goals_attempted' => $fga,
            'three_point_field_goals_made' => $tpm,
            'three_point_field_goals_attempted' => $tpa,
            'free_throws_made' => $ftm,
            'free_throws_attempted' => $fta,
            'offensive_rebounds' => $this->toInt($byName['offensiveRebounds'] ?? 0),
            'defensive_rebounds' => $this->toInt($byName['defensiveRebounds'] ?? 0),
            'rebounds' => $this->toInt($byName['totalRebounds'] ?? 0),
            'assists' => $this->toInt($byName['assists'] ?? 0),
            'steals' => $this->toInt($byName['steals'] ?? 0),
            'blocks' => $this->toInt($byName['blocks'] ?? 0),
            'turnovers' => $this->toInt($byName['turnovers'] ?? 0),
            'fouls' => $this->toInt($byName['fouls'] ?? 0),
        ];
    }

    /**
     * @param  array<int, string>  $labels
     * @param  array<int, string>  $names
     * @param  array<int, string|int|float>  $values
     * @return array<string, mixed>
     */
    private function mapGamelogStats(array $labels, array $names, array $values): array
    {
        $byName = [];
        foreach ($names as $index => $name) {
            $byName[$name] = $values[$index] ?? null;
        }

        [$fgm, $fga] = $this->parseMadeAttempted($byName['fieldGoalsMade-fieldGoalsAttempted'] ?? '0-0');
        [$tpm, $tpa] = $this->parseMadeAttempted($byName['threePointFieldGoalsMade-threePointFieldGoalsAttempted'] ?? '0-0');
        [$ftm, $fta] = $this->parseMadeAttempted($byName['freeThrowsMade-freeThrowsAttempted'] ?? '0-0');

        return [
            'minutes' => $this->toFloat($byName['minutes'] ?? 0),
            'points' => $this->toInt($byName['points'] ?? 0),
            'rebounds' => $this->toInt($byName['totalRebounds'] ?? 0),
            'assists' => $this->toInt($byName['assists'] ?? 0),
            'steals' => $this->toInt($byName['steals'] ?? 0),
            'blocks' => $this->toInt($byName['blocks'] ?? 0),
            'turnovers' => $this->toInt($byName['turnovers'] ?? 0),
            'field_goals_made' => $fgm,
            'field_goals_attempted' => $fga,
            'three_point_field_goals_made' => $tpm,
            'three_point_field_goals_attempted' => $tpa,
            'free_throws_made' => $ftm,
            'free_throws_attempted' => $fta,
            'fouls' => $this->toInt($byName['fouls'] ?? 0),
            'labels' => $labels,
        ];
    }

    /**
     * @param  array<string, mixed>  $team
     * @param  array<string, mixed>  $own
     * @param  array<string, mixed>  $opp
     * @param  array<string, mixed>  $athlete
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function playerBoxRow(
        string $gameId,
        mixed $season,
        mixed $seasonType,
        ?string $gameDate,
        mixed $gameDateTime,
        string $side,
        array $team,
        array $own,
        array $opp,
        int $teamScore,
        int $opponentScore,
        array $athlete,
        array $stats,
        bool $starter,
        bool $active,
        bool $ejected,
        bool $didNotPlay,
        ?string $reason,
    ): array {
        return [
            'game_id' => $gameId,
            'season' => $season,
            'season_type' => $seasonType,
            'game_date' => $gameDate,
            'game_date_time' => $gameDateTime,
            'athlete_id' => $athlete['id'] ?? null,
            'athlete_display_name' => $athlete['displayName'] ?? null,
            'athlete_short_name' => $athlete['shortName'] ?? null,
            'athlete_jersey' => $athlete['jersey'] ?? null,
            'athlete_headshot_href' => $athlete['headshot']['href'] ?? null,
            'athlete_position_name' => $athlete['position']['displayName'] ?? null,
            'athlete_position_abbreviation' => $athlete['position']['abbreviation'] ?? null,
            'team_id' => $team['id'] ?? null,
            'team_name' => $team['name'] ?? null,
            'team_location' => $team['location'] ?? null,
            'team_display_name' => $team['displayName'] ?? null,
            'team_uid' => $team['uid'] ?? null,
            'team_slug' => $team['slug'] ?? null,
            'team_logo' => $team['logo'] ?? null,
            'team_abbreviation' => $team['abbreviation'] ?? null,
            'team_color' => $team['color'] ?? null,
            'team_alternate_color' => $team['alternateColor'] ?? null,
            'home_away' => $side,
            'team_winner' => ($own['winner'] ?? false) === true,
            'team_score' => $teamScore,
            'opponent_team_id' => $opp['team']['id'] ?? null,
            'opponent_team_name' => $opp['team']['name'] ?? null,
            'opponent_team_location' => $opp['team']['location'] ?? null,
            'opponent_team_display_name' => $opp['team']['displayName'] ?? null,
            'opponent_team_abbreviation' => $opp['team']['abbreviation'] ?? null,
            'opponent_team_logo' => $opp['team']['logo'] ?? null,
            'opponent_team_score' => $opponentScore,
            'starter' => $starter,
            'active' => $active,
            'ejected' => $ejected,
            'did_not_play' => $didNotPlay,
            'reason' => $reason,
            ...$stats,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseMadeAttempted(mixed $value): array
    {
        if (is_string($value) && str_contains($value, '-')) {
            [$made, $attempted] = explode('-', $value, 2);

            return [$this->toInt($made), $this->toInt($attempted)];
        }

        return [0, 0];
    }

    private function toInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) $value;
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return array{
     *     season_stats: array<string, mixed>|null,
     *     splits: array<int, array<string, mixed>>,
     *     news: array<int, array<string, mixed>>,
     *     next_game: array<string, mixed>|null,
     *     fantasy_outlook: string|null
     * }
     */
    public function mapAthleteOverview(array $overview): array
    {
        $statistics = $overview['statistics'] ?? [];
        $labels = $statistics['labels'] ?? [];
        $names = $statistics['names'] ?? [];

        $splits = [];
        foreach ($statistics['splits'] ?? [] as $split) {
            $stats = [];
            $values = $split['stats'] ?? [];
            foreach ($names as $index => $name) {
                $stats[$name] = $values[$index] ?? null;
            }

            $splits[] = [
                'name' => $split['displayName'] ?? $split['type'] ?? null,
                'labels' => $labels,
                'stats' => $stats,
            ];
        }

        $seasonStats = null;
        foreach ($splits as $split) {
            if (($split['name'] ?? '') === 'Regular Season') {
                $seasonStats = $split['stats'];
                break;
            }
        }

        if ($seasonStats === null && $splits !== []) {
            $seasonStats = $splits[0]['stats'];
        }

        $news = [];
        foreach ($overview['news'] ?? [] as $item) {
            $news[] = $this->mapNewsItem($item);
        }

        $nextGame = null;
        $events = $overview['nextGame']['league']['events'] ?? [];
        if (is_array($events) && $events !== []) {
            $event = $events[0];
            $nextGame = [
                'game_id' => $event['id'] ?? null,
                'name' => $event['name'] ?? null,
                'short_name' => $event['shortName'] ?? null,
                'date' => $event['date'] ?? null,
                'venue' => $event['location'] ?? null,
                'url' => $this->extractEventUrl($event),
            ];
        }

        $fantasy = $overview['fantasy']['outlook'] ?? $overview['rotowire']['outlook'] ?? null;

        return [
            'season_stats' => $seasonStats,
            'splits' => $splits,
            'news' => $news,
            'next_game' => $nextGame,
            'fantasy_outlook' => is_string($fantasy) ? $fantasy : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function mapLeagueNews(array $payload, ?int $limit = null): array
    {
        $items = [];
        foreach ($payload['articles'] ?? [] as $article) {
            $items[] = $this->mapNewsItem($article);
        }

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     season: array<string, mixed>|null,
     *     teams: array<int, array<string, mixed>>
     * }
     */
    public function mapLeagueInjuries(array $payload): array
    {
        $teams = [];

        foreach ($payload['injuries'] ?? [] as $teamBlock) {
            $injuries = [];
            foreach ($teamBlock['injuries'] ?? [] as $injury) {
                $athlete = $injury['athlete'] ?? [];
                $injuries[] = [
                    'id' => $injury['id'] ?? null,
                    'status' => $injury['status'] ?? null,
                    'date' => $injury['date'] ?? null,
                    'short_comment' => $injury['shortComment'] ?? null,
                    'long_comment' => $injury['longComment'] ?? null,
                    'athlete_id' => $athlete['id'] ?? null,
                    'athlete_display_name' => $athlete['displayName'] ?? null,
                    'athlete_short_name' => $athlete['shortName'] ?? null,
                    'athlete_position' => $athlete['position']['abbreviation'] ?? null,
                    'athlete_headshot' => $athlete['headshot']['href'] ?? null,
                ];
            }

            $teams[] = [
                'team_id' => $teamBlock['id'] ?? null,
                'team_name' => $teamBlock['displayName'] ?? null,
                'injuries' => $injuries,
            ];
        }

        return [
            'season' => $payload['season'] ?? null,
            'teams' => $teams,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function filterPlayerInjuries(array $payload, string $athleteId): array
    {
        $mapped = $this->mapLeagueInjuries($payload);
        $items = [];

        foreach ($mapped['teams'] as $team) {
            foreach ($team['injuries'] as $injury) {
                if ((string) ($injury['athlete_id'] ?? '') === $athleteId) {
                    $items[] = array_merge($injury, [
                        'team_id' => $team['team_id'],
                        'team_name' => $team['team_name'],
                    ]);
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapNewsItem(array $item): array
    {
        $image = $item['images'][0]['url'] ?? null;
        $url = $item['links']['web']['href']
            ?? $item['links']['api']['self']['href']
            ?? null;

        return [
            'id' => $item['id'] ?? $item['nowId'] ?? null,
            'headline' => $item['headline'] ?? null,
            'description' => $item['description'] ?? null,
            'published' => $item['published'] ?? $item['lastModified'] ?? null,
            'type' => $item['type'] ?? null,
            'image_url' => $image,
            'url' => $url,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function extractEventUrl(array $event): ?string
    {
        foreach ($event['links'] ?? [] as $link) {
            if (in_array('summary', $link['rel'] ?? [], true) && ! empty($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }
}
