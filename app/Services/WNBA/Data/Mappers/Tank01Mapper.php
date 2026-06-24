<?php

namespace App\Services\WNBA\Data\Mappers;

class Tank01Mapper
{
    public function __construct(
        private int $seasonYear
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $teamsBody
     * @return array<int, array<string, mixed>>
     */
    public function mapScheduleFromTeams(array $teamsBody): array
    {
        $records = [];
        $seen = [];

        foreach ($teamsBody as $team) {
            foreach (($team['schedule'] ?? []) as $gameId => $game) {
                if (isset($seen[$gameId])) {
                    continue;
                }
                $seen[$gameId] = true;

                $records[] = $this->mapScheduleGame($gameId, $game, $teamsBody);
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $game
     * @param  array<int, array<string, mixed>>  $teamsBody
     * @return array<string, mixed>
     */
    public function mapScheduleGame(string $gameId, array $game, array $teamsBody = []): array
    {
        $awayAbv = $game['away'] ?? $this->teamAbvFromGameId($gameId, 'away');
        $homeAbv = $game['home'] ?? $this->teamAbvFromGameId($gameId, 'home');
        $awayTeam = $this->findTeam($teamsBody, $awayAbv, $game['teamIDAway'] ?? null);
        $homeTeam = $this->findTeam($teamsBody, $homeAbv, $game['teamIDHome'] ?? null);
        $gameDate = $this->formatGameDate($game['gameDate'] ?? substr($gameId, 0, 8));

        return [
            'game_id' => $gameId,
            'season' => $this->seasonYear,
            'season_type' => $game['seasonType'] ?? 'Regular Season',
            'game_date' => $gameDate,
            'game_date_time' => $game['gameTime'] ?? $gameDate,
            'home_team_id' => $homeTeam['teamID'] ?? $game['teamIDHome'] ?? null,
            'home_team_name' => $homeTeam['teamName'] ?? $homeAbv,
            'home_team_location' => $homeTeam['teamCity'] ?? null,
            'home_team_abbreviation' => $homeAbv,
            'home_team_display_name' => $this->teamDisplayName($homeTeam, $homeAbv),
            'away_team_id' => $awayTeam['teamID'] ?? $game['teamIDAway'] ?? null,
            'away_team_name' => $awayTeam['teamName'] ?? $awayAbv,
            'away_team_location' => $awayTeam['teamCity'] ?? null,
            'away_team_abbreviation' => $awayAbv,
            'away_team_display_name' => $this->teamDisplayName($awayTeam, $awayAbv),
            'venue_name' => $game['arena'] ?? null,
            'venue_city' => null,
            'venue_state' => null,
            'venue_country' => null,
            'venue_id' => null,
            'venue_capacity' => null,
            'venue_surface' => null,
            'venue_indoor' => true,
            'status_name' => $game['gameStatus'] ?? null,
            'status_type' => $this->mapGameStatusType($game['gameStatus'] ?? ''),
            'status_abbreviation' => $game['gameStatusCode'] ?? null,
            'status_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $boxScoreBody
     * @return array{player: array<int, array<string, mixed>>, team: array<int, array<string, mixed>>}
     */
    public function mapBoxScore(array $boxScoreBody): array
    {
        $gameId = $boxScoreBody['gameID'] ?? '';
        $awayAbv = $boxScoreBody['away'] ?? $this->teamAbvFromGameId($gameId, 'away');
        $homeAbv = $boxScoreBody['home'] ?? $this->teamAbvFromGameId($gameId, 'home');
        $gameDate = $this->formatGameDate($boxScoreBody['gameDate'] ?? substr($gameId, 0, 8));

        $playerRecords = [];
        foreach (['away' => $awayAbv, 'home' => $homeAbv] as $side => $abv) {
            $opponentSide = $side === 'home' ? 'away' : 'home';
            $opponentAbv = $side === 'home' ? $awayAbv : $homeAbv;
            $teamId = $side === 'home'
                ? ($boxScoreBody['teamIDHome'] ?? null)
                : ($boxScoreBody['teamIDAway'] ?? null);
            $opponentId = $side === 'home'
                ? ($boxScoreBody['teamIDAway'] ?? null)
                : ($boxScoreBody['teamIDHome'] ?? null);
            $teamScore = (int) ($side === 'home' ? ($boxScoreBody['homePts'] ?? 0) : ($boxScoreBody['awayPts'] ?? 0));
            $opponentScore = (int) ($side === 'home' ? ($boxScoreBody['awayPts'] ?? 0) : ($boxScoreBody['homePts'] ?? 0));

            $players = $boxScoreBody['playerStats'][$abv] ?? [];
            foreach ($players as $player) {
                $playerRecords[] = $this->mapPlayerBoxScoreRow(
                    $gameId,
                    $gameDate,
                    $side,
                    $abv,
                    $teamId,
                    $opponentAbv,
                    $opponentId,
                    $teamScore,
                    $opponentScore,
                    $player
                );
            }
        }

        $teamRecords = [];
        foreach (['away' => $awayAbv, 'home' => $homeAbv] as $side => $abv) {
            $opponentSide = $side === 'home' ? 'away' : 'home';
            $opponentAbv = $side === 'home' ? $awayAbv : $homeAbv;
            $stats = $boxScoreBody['teamStats'][$abv] ?? [];
            if (empty($stats)) {
                continue;
            }

            $teamRecords[] = $this->mapTeamBoxScoreRow(
                $gameId,
                $gameDate,
                $side,
                $abv,
                $side === 'home' ? ($boxScoreBody['teamIDHome'] ?? null) : ($boxScoreBody['teamIDAway'] ?? null),
                $opponentAbv,
                $side === 'home' ? ($boxScoreBody['teamIDAway'] ?? null) : ($boxScoreBody['teamIDHome'] ?? null),
                (int) ($side === 'home' ? ($boxScoreBody['homePts'] ?? 0) : ($boxScoreBody['awayPts'] ?? 0)),
                (int) ($side === 'home' ? ($boxScoreBody['awayPts'] ?? 0) : ($boxScoreBody['homePts'] ?? 0)),
                $stats
            );
        }

        return ['player' => $playerRecords, 'team' => $teamRecords];
    }

    /**
     * @param  array<int, array<string, mixed>>  $teamsBody
     * @return array<int, array<string, mixed>>
     */
    public function mapTeams(array $teamsBody): array
    {
        $records = [];

        foreach ($teamsBody as $team) {
            $records[] = [
                'team_id' => $team['teamID'] ?? null,
                'team_name' => $team['teamName'] ?? null,
                'team_location' => $team['teamCity'] ?? null,
                'team_abbreviation' => $team['teamAbv'] ?? null,
                'team_display_name' => $this->teamDisplayName($team, $team['teamAbv'] ?? ''),
                'conference' => $team['conference'] ?? null,
                'wins' => (int) ($team['wins'] ?? 0),
                'losses' => (int) ($team['losses'] ?? 0),
            ];
        }

        return $records;
    }

    /**
     * @param  array<int, array<string, mixed>>  $teamsBody
     * @return array<int, array<string, mixed>>
     */
    public function mapRosterPlayers(array $teamsBody): array
    {
        $records = [];

        foreach ($teamsBody as $team) {
            foreach (($team['roster'] ?? []) as $player) {
                $records[] = [
                    'athlete_id' => $player['playerID'] ?? null,
                    'athlete_display_name' => $player['longName'] ?? null,
                    'athlete_short_name' => $player['longName'] ?? null,
                    'athlete_jersey' => $player['jerseyNum'] ?? null,
                    'athlete_position_name' => $player['pos'] ?? null,
                    'athlete_position_abbreviation' => $player['pos'] ?? null,
                    'team_id' => $team['teamID'] ?? null,
                ];
            }
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    public function mapPlayerGamelog(array $body, string $playerId, int $season): array
    {
        $rows = [];

        foreach ($body as $gameKey => $game) {
            if (! is_array($game)) {
                continue;
            }

            $gameId = (string) ($game['gameID'] ?? $gameKey);
            if ($gameId === '') {
                continue;
            }

            $awayAbv = $this->teamAbvFromGameId($gameId, 'away');
            $homeAbv = $this->teamAbvFromGameId($gameId, 'home');
            $teamAbv = (string) ($game['teamAbv'] ?? $game['team'] ?? '');
            $side = $teamAbv === $homeAbv ? 'home' : 'away';
            $opponentAbv = $side === 'home' ? $awayAbv : $homeAbv;
            $gameDate = $this->formatGameDate($game['gameDate'] ?? substr($gameId, 0, 8));

            $rows[] = $this->mapPlayerBoxScoreRow(
                $gameId,
                $gameDate,
                $side,
                $teamAbv,
                $game['teamID'] ?? null,
                $opponentAbv,
                null,
                0,
                0,
                array_merge($game, ['playerID' => $game['playerID'] ?? $playerId]),
            );
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) $b['game_id'], (string) $a['game_id']));

        foreach ($rows as &$row) {
            $row['season'] = $season;
        }
        unset($row);

        return $rows;
    }

    public function isGameCompleted(string $status): bool
    {
        $status = strtolower($status);

        return str_contains($status, 'final')
            || str_contains($status, 'completed')
            || $status === '2';
    }

    public function isGameLive(string $status): bool
    {
        $status = strtolower($status);

        return str_contains($status, 'progress')
            || str_contains($status, 'live')
            || $status === '1';
    }

    /**
     * @param  array<string, mixed>  $player
     * @return array<string, mixed>
     */
    private function mapPlayerBoxScoreRow(
        string $gameId,
        string $gameDate,
        string $side,
        string $teamAbv,
        ?string $teamId,
        string $opponentAbv,
        ?string $opponentId,
        int $teamScore,
        int $opponentScore,
        array $player
    ): array {
        return [
            'game_id' => $gameId,
            'season' => $this->seasonYear,
            'season_type' => 'Regular Season',
            'game_date' => $gameDate,
            'game_date_time' => $gameDate,
            'athlete_id' => $player['playerID'] ?? null,
            'athlete_display_name' => $player['longName'] ?? null,
            'team_id' => $teamId ?? $player['teamID'] ?? null,
            'team_name' => $player['team'] ?? $teamAbv,
            'team_display_name' => $player['team'] ?? $teamAbv,
            'team_location' => $player['team'] ?? $teamAbv,
            'team_abbreviation' => $teamAbv,
            'minutes' => $player['mins'] ?? 0,
            'field_goals_made' => (int) ($player['fgm'] ?? 0),
            'field_goals_attempted' => (int) ($player['fga'] ?? 0),
            'three_point_field_goals_made' => (int) ($player['tptfgm'] ?? 0),
            'three_point_field_goals_attempted' => (int) ($player['tptfga'] ?? 0),
            'free_throws_made' => (int) ($player['ftm'] ?? 0),
            'free_throws_attempted' => (int) ($player['fta'] ?? 0),
            'offensive_rebounds' => (int) ($player['OffReb'] ?? 0),
            'defensive_rebounds' => (int) ($player['DefReb'] ?? 0),
            'rebounds' => (int) ($player['reb'] ?? ((int) ($player['OffReb'] ?? 0) + (int) ($player['DefReb'] ?? 0))),
            'assists' => (int) ($player['ast'] ?? 0),
            'steals' => (int) ($player['stl'] ?? 0),
            'blocks' => (int) ($player['blk'] ?? 0),
            'turnovers' => (int) ($player['TOV'] ?? 0),
            'fouls' => (int) ($player['pf'] ?? $player['PF'] ?? 0),
            'plus_minus' => (int) preg_replace('/[^0-9-]/', '', (string) ($player['plusMinus'] ?? '0')),
            'points' => (int) ($player['pts'] ?? 0),
            'starter' => ($player['starter'] ?? '0') === '1' || ($player['starter'] ?? false) === true,
            'active' => true,
            'athlete_jersey' => $player['jerseyNum'] ?? null,
            'athlete_short_name' => $player['longName'] ?? null,
            'athlete_position_name' => $player['pos'] ?? null,
            'athlete_position_abbreviation' => $player['pos'] ?? null,
            'home_away' => $side,
            'team_winner' => $teamScore > $opponentScore,
            'team_score' => $teamScore,
            'opponent_team_id' => $opponentId,
            'opponent_team_name' => $opponentAbv,
            'opponent_team_display_name' => $opponentAbv,
            'opponent_team_abbreviation' => $opponentAbv,
            'opponent_team_score' => $opponentScore,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    private function mapTeamBoxScoreRow(
        string $gameId,
        string $gameDate,
        string $side,
        string $teamAbv,
        ?string $teamId,
        string $opponentAbv,
        ?string $opponentId,
        int $teamScore,
        int $opponentScore,
        array $stats
    ): array {
        return [
            'game_id' => $gameId,
            'season' => $this->seasonYear,
            'season_type' => 'Regular Season',
            'game_date' => $gameDate,
            'game_date_time' => $gameDate,
            'team_id' => $teamId,
            'team_name' => $teamAbv,
            'team_location' => $teamAbv,
            'team_abbreviation' => $teamAbv,
            'team_display_name' => $teamAbv,
            'home_away' => $side,
            'team_winner' => $teamScore > $opponentScore,
            'team_score' => $teamScore,
            'opponent_team_id' => $opponentId,
            'opponent_team_name' => $opponentAbv,
            'opponent_team_location' => $opponentAbv,
            'opponent_team_display_name' => $opponentAbv,
            'opponent_team_abbreviation' => $opponentAbv,
            'opponent_team_score' => $opponentScore,
            'field_goals_made' => (int) ($stats['fgm'] ?? 0),
            'field_goals_attempted' => (int) ($stats['fga'] ?? 0),
            'three_point_field_goals_made' => (int) ($stats['tptfgm'] ?? 0),
            'three_point_field_goals_attempted' => (int) ($stats['tptfga'] ?? 0),
            'free_throws_made' => (int) ($stats['ftm'] ?? 0),
            'free_throws_attempted' => (int) ($stats['fta'] ?? 0),
            'offensive_rebounds' => (int) ($stats['OffReb'] ?? 0),
            'defensive_rebounds' => (int) ($stats['DefReb'] ?? 0),
            'rebounds' => (int) ($stats['reb'] ?? 0),
            'assists' => (int) ($stats['ast'] ?? 0),
            'steals' => (int) ($stats['stl'] ?? 0),
            'blocks' => (int) ($stats['blk'] ?? 0),
            'turnovers' => (int) ($stats['TOV'] ?? 0),
            'fouls' => (int) ($stats['pf'] ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $teams
     * @return array<string, mixed>
     */
    private function findTeam(array $teams, string $abv, ?string $teamId): array
    {
        foreach ($teams as $team) {
            if (($team['teamAbv'] ?? '') === $abv) {
                return $team;
            }
            if ($teamId !== null && (string) ($team['teamID'] ?? '') === (string) $teamId) {
                return $team;
            }
        }

        return [];
    }

    private function teamDisplayName(array $team, string $fallback): string
    {
        if (empty($team)) {
            return $fallback;
        }

        $city = $team['teamCity'] ?? '';
        $name = $team['teamName'] ?? '';

        return trim("{$city} {$name}") ?: $fallback;
    }

    private function formatGameDate(string $raw): string
    {
        if (strlen($raw) === 8 && ctype_digit($raw)) {
            return substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2);
        }

        return substr($raw, 0, 10);
    }

    private function teamAbvFromGameId(string $gameId, string $side): string
    {
        if (! str_contains($gameId, '@')) {
            return '';
        }

        [$dateAway, $home] = explode('@', $gameId, 2);
        $away = substr($dateAway, 8);

        return $side === 'away' ? $away : $home;
    }

    private function mapGameStatusType(string $status): string
    {
        if ($this->isGameLive($status)) {
            return 'in_progress';
        }
        if ($this->isGameCompleted($status)) {
            return 'final';
        }

        return 'scheduled';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    public function mapInjuries(array $body): array
    {
        $records = [];
        $items = $body['injuries'] ?? $body;

        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $records[] = [
                'player_id' => $item['playerID'] ?? $item['playerId'] ?? null,
                'player_name' => $item['longName'] ?? $item['playerName'] ?? null,
                'team_id' => $item['teamID'] ?? null,
                'team_abbreviation' => $item['teamAbv'] ?? null,
                'status' => $item['designation'] ?? $item['status'] ?? ($item['injury']['designation'] ?? null),
                'description' => $item['description'] ?? ($item['injury']['description'] ?? null),
                'injury_date' => $item['injDate'] ?? $item['injuryDate'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    public function mapNews(array $body): array
    {
        $records = [];
        $items = $body['news'] ?? $body['articles'] ?? $body;

        if (! is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $records[] = [
                'id' => $item['newsID'] ?? $item['id'] ?? null,
                'headline' => $item['title'] ?? $item['headline'] ?? null,
                'description' => $item['description'] ?? $item['content'] ?? null,
                'published' => $item['publishedDate'] ?? $item['published'] ?? $item['date'] ?? null,
                'url' => $item['link'] ?? $item['url'] ?? null,
                'player_id' => $item['playerID'] ?? null,
                'team_id' => $item['teamID'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    public function mapPlayerInfo(array $body, string $playerId): ?array
    {
        $player = $body[$playerId] ?? $body['player'] ?? null;

        if ($player === null && isset($body['body']) && is_array($body['body'])) {
            $player = $body['body'][$playerId] ?? null;
        }

        if (! is_array($player)) {
            foreach ($body as $key => $value) {
                if (is_array($value) && (($value['playerID'] ?? null) === $playerId)) {
                    $player = $value;
                    break;
                }
            }
        }

        if (! is_array($player)) {
            return null;
        }

        $stats = $player['stats'] ?? $player['seasonStats'] ?? [];

        return [
            'player_id' => $player['playerID'] ?? $playerId,
            'name' => $player['longName'] ?? $player['playerName'] ?? null,
            'position' => $player['pos'] ?? null,
            'jersey' => $player['jerseyNum'] ?? null,
            'team_id' => $player['teamID'] ?? null,
            'team_abbreviation' => $player['teamAbv'] ?? null,
            'injury' => [
                'status' => $player['injury']['designation'] ?? null,
                'description' => $player['injury']['description'] ?? null,
            ],
            'season_stats' => is_array($stats) ? $stats : [],
        ];
    }
}
