<?php

namespace App\Services\WNBA\Data\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SportsBlazeFetcher
{
    private int $dataSeasonYear;

    public function __construct()
    {
        $this->dataSeasonYear = (int) config('wnba.seasons.current_season');
    }

    /**
     * @return array<int, array{year: int, season: string}>
     */
    public function fetchAvailableSeasons(): array
    {
        $league = $this->sportsBlazeLeagueId();
        $cacheBase = rtrim((string) config('wnba.data_source.cache_base_url'), '/');
        $response = Http::acceptJson()
            ->timeout((int) config('wnba.api.timeout', 30))
            ->get("{$cacheBase}/seasons/{$league}");

        if (! $response->successful()) {
            throw new \Exception("Failed to fetch SportsBlaze seasons for {$league}");
        }

        return $response->json('seasons', []);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function fetchPlayerBoxscores(int $season, array $options = []): array
    {
        $games = $this->fetchCompletedGameBoxscores($season);

        return $this->parseBoxScores(['games' => $games]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function fetchTeamBoxscores(int $season, array $options = []): array
    {
        $games = $this->fetchCompletedGameBoxscores($season);

        return $this->parseTeamBoxScores(['games' => $games]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchSchedule(int $season): array
    {
        $url = $this->withSportsBlazeKey($this->feedUrl(
            'schedule',
            $this->sportsBlazeV1Path("schedule/season/{$season}.json"),
            ''
        ));
        $response = Http::acceptJson()->timeout((int) config('wnba.api.timeout', 30))->get($url);

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch SportsBlaze schedule');
        }

        return $this->parseSchedule(json_decode($response->body(), true) ?: []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCompletedGameBoxscores(int $season): array
    {
        $url = $this->withSportsBlazeKey($this->feedUrl(
            'schedule',
            $this->sportsBlazeV1Path("schedule/season/{$season}.json"),
            ''
        ));
        $response = Http::acceptJson()->timeout((int) config('wnba.api.timeout', 30))->get($url);

        if (! $response->successful()) {
            throw new \Exception('Failed to fetch SportsBlaze schedule for box scores');
        }

        $schedule = json_decode($response->body(), true);
        $games = [];

        foreach (($schedule['games'] ?? []) as $game) {
            if (empty($game['id']) || ($game['status'] ?? null) === 'Scheduled') {
                continue;
            }

            $boxUrl = $this->withSportsBlazeKey($this->feedUrl(
                'player_boxscores',
                $this->sportsBlazeV1Path("boxscores/game/{$game['id']}.json"),
                ''
            ));
            $boxResponse = Http::acceptJson()->timeout((int) config('wnba.api.timeout', 30))->get($boxUrl);
            if ($boxResponse->successful()) {
                $games[] = json_decode($boxResponse->body(), true);
            }
        }

        return $games;
    }

    private function sportsBlazeLeagueId(): string
    {
        return (string) config('wnba.data_source.league_id', 'wnba');
    }

    private function sportsBlazeV1Path(string $suffix): string
    {
        return $this->sportsBlazeLeagueId().'/v1/'.ltrim($suffix, '/');
    }

    private function feedUrl(string $feed, string $sportsBlazePath, string $legacyUrl): string
    {
        $configuredUrl = config("wnba.data_source.feeds.{$feed}");
        if (! empty($configuredUrl)) {
            return $configuredUrl;
        }

        if ($sportsBlazePath !== '') {
            $baseUrl = rtrim((string) config('wnba.data_source.base_url', 'https://api.sportsblaze.com'), '/');

            return $baseUrl.'/'.ltrim($sportsBlazePath, '/');
        }

        return $legacyUrl;
    }

    private function withSportsBlazeKey(string $url): string
    {
        $key = config('wnba.data_source.api_key');
        if (empty($key)) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'key='.urlencode((string) $key);
    }

    private function sportsBlazeGamesList(array $payload): array
    {
        return $payload['games'] ?? $payload['events'] ?? [];
    }

    private function sportsBlazeTeamScore(array $game, string $side): int
    {
        $total = $game['scores']['total'][$side] ?? null;

        if (is_array($total)) {
            return (int) ($total['points'] ?? 0);
        }

        return (int) ($total ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseBoxScores(array $payload): array
    {
        $records = [];
        foreach ($this->sportsBlazeGamesList($payload) as $game) {
            $game = isset($game['id']) && isset($game['rosters']) ? $game : ($game ?? []);
            foreach (['away', 'home'] as $side) {
                $team = $game['teams'][$side] ?? [];
                $opponentSide = $side === 'home' ? 'away' : 'home';
                $opponent = $game['teams'][$opponentSide] ?? [];
                foreach (($game['rosters'][$side] ?? []) as $player) {
                    $stats = $player['stats'] ?? [];
                    $records[] = [
                        'game_id' => $game['id'] ?? null,
                        'season' => $game['season']['year'] ?? $this->dataSeasonYear,
                        'season_type' => $game['season']['type'] ?? null,
                        'game_date' => isset($game['date']) ? substr($game['date'], 0, 10) : null,
                        'game_date_time' => $game['date'] ?? null,
                        'athlete_id' => $player['id'] ?? null,
                        'athlete_display_name' => $player['name'] ?? null,
                        'team_id' => $team['id'] ?? null,
                        'team_name' => $team['name'] ?? null,
                        'team_display_name' => $team['name'] ?? null,
                        'team_location' => $team['name'] ?? null,
                        'team_abbreviation' => $team['abbreviation'] ?? null,
                        'minutes' => $stats['time_on_court'] ?? ($stats['minutes'] ?? null),
                        'field_goals_made' => $stats['field_goals_made'] ?? 0,
                        'field_goals_attempted' => $stats['field_goals_attempts'] ?? 0,
                        'three_point_field_goals_made' => $stats['three_pointers_made'] ?? 0,
                        'three_point_field_goals_attempted' => $stats['three_pointers_attempts'] ?? 0,
                        'free_throws_made' => $stats['free_throws_made'] ?? 0,
                        'free_throws_attempted' => $stats['free_throws_attempts'] ?? 0,
                        'offensive_rebounds' => $stats['rebounds_offensive'] ?? 0,
                        'defensive_rebounds' => $stats['rebounds_defensive'] ?? 0,
                        'rebounds' => $stats['rebounds'] ?? 0,
                        'assists' => $stats['assists'] ?? 0,
                        'steals' => $stats['steals'] ?? 0,
                        'blocks' => $stats['blocks'] ?? 0,
                        'turnovers' => $stats['turnovers_personal'] ?? ($stats['turnovers'] ?? 0),
                        'fouls' => $stats['fouls_personal'] ?? 0,
                        'plus_minus' => $stats['plus_minus'] ?? 0,
                        'points' => $stats['points'] ?? 0,
                        'starter' => $player['started'] ?? false,
                        'active' => $player['played'] ?? true,
                        'athlete_jersey' => $player['number'] ?? null,
                        'athlete_short_name' => $player['name'] ?? null,
                        'athlete_position_name' => $player['position'] ?? null,
                        'athlete_position_abbreviation' => $player['position'] ?? null,
                        'home_away' => $side,
                        'team_winner' => null,
                        'team_score' => $this->sportsBlazeTeamScore($game, $side),
                        'opponent_team_id' => $opponent['id'] ?? null,
                        'opponent_team_name' => $opponent['name'] ?? null,
                        'opponent_team_display_name' => $opponent['name'] ?? null,
                        'opponent_team_abbreviation' => $opponent['abbreviation'] ?? null,
                        'opponent_team_score' => $this->sportsBlazeTeamScore($game, $opponentSide),
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseSchedule(array $payload): array
    {
        $records = [];
        foreach ($this->sportsBlazeGamesList($payload) as $game) {
            $venueParts = array_map('trim', explode(',', $game['venue']['location'] ?? ''));
            $records[] = [
                'game_id' => $game['id'] ?? null,
                'season' => $game['season']['year'] ?? $this->dataSeasonYear,
                'season_type' => $game['season']['type'] ?? null,
                'game_date' => isset($game['date']) ? substr($game['date'], 0, 10) : null,
                'game_date_time' => $game['date'] ?? null,
                'home_team_id' => $game['teams']['home']['id'] ?? null,
                'home_team_name' => $game['teams']['home']['name'] ?? null,
                'home_team_location' => $game['teams']['home']['name'] ?? null,
                'home_team_abbreviation' => $game['teams']['home']['abbreviation'] ?? null,
                'home_team_display_name' => $game['teams']['home']['name'] ?? null,
                'away_team_id' => $game['teams']['away']['id'] ?? null,
                'away_team_name' => $game['teams']['away']['name'] ?? null,
                'away_team_location' => $game['teams']['away']['name'] ?? null,
                'away_team_abbreviation' => $game['teams']['away']['abbreviation'] ?? null,
                'away_team_display_name' => $game['teams']['away']['name'] ?? null,
                'venue_name' => $game['venue']['name'] ?? null,
                'venue_city' => $venueParts[0] ?? null,
                'venue_state' => $venueParts[1] ?? null,
                'venue_country' => null,
                'venue_id' => null,
                'venue_capacity' => null,
                'venue_surface' => null,
                'venue_indoor' => true,
                'status_name' => $game['status'] ?? null,
                'status_type' => $game['status'] ?? null,
                'status_abbreviation' => $game['status'] ?? null,
                'status_id' => null,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseTeamBoxScores(array $payload): array
    {
        $games = isset($payload['games']) ? $payload['games'] : [$payload];
        $records = [];

        foreach ($games as $game) {
            if (empty($game['id']) || empty($game['stats'])) {
                continue;
            }

            foreach (['away', 'home'] as $side) {
                $team = $game['teams'][$side] ?? [];
                $opponentSide = $side === 'home' ? 'away' : 'home';
                $opponent = $game['teams'][$opponentSide] ?? [];
                $stats = $game['stats'][$side] ?? [];

                $records[] = [
                    'game_id' => $game['id'],
                    'season' => $game['season']['year'] ?? $this->dataSeasonYear,
                    'season_type' => $game['season']['type'] ?? null,
                    'game_date' => isset($game['date']) ? substr($game['date'], 0, 10) : null,
                    'game_date_time' => $game['date'] ?? null,
                    'team_id' => $team['id'] ?? null,
                    'team_name' => $team['name'] ?? null,
                    'team_location' => $team['name'] ?? null,
                    'team_abbreviation' => $team['abbreviation'] ?? null,
                    'team_display_name' => $team['name'] ?? null,
                    'home_away' => $side,
                    'team_winner' => null,
                    'team_score' => $this->sportsBlazeTeamScore($game, $side),
                    'opponent_team_id' => $opponent['id'] ?? null,
                    'opponent_team_name' => $opponent['name'] ?? null,
                    'opponent_team_location' => $opponent['name'] ?? null,
                    'opponent_team_display_name' => $opponent['name'] ?? null,
                    'opponent_team_abbreviation' => $opponent['abbreviation'] ?? null,
                    'opponent_team_score' => $this->sportsBlazeTeamScore($game, $opponentSide),
                    'field_goals_made' => $stats['field_goals_made'] ?? 0,
                    'field_goals_attempted' => $stats['field_goals_attempts'] ?? 0,
                    'three_point_field_goals_made' => $stats['three_pointers_made'] ?? 0,
                    'three_point_field_goals_attempted' => $stats['three_pointers_attempts'] ?? 0,
                    'free_throws_made' => $stats['free_throws_made'] ?? 0,
                    'free_throws_attempted' => $stats['free_throws_attempts'] ?? 0,
                    'offensive_rebounds' => $stats['rebounds_offensive'] ?? 0,
                    'defensive_rebounds' => $stats['rebounds_defensive'] ?? 0,
                    'rebounds' => $stats['rebounds'] ?? 0,
                    'assists' => $stats['assists'] ?? 0,
                    'steals' => $stats['steals'] ?? 0,
                    'blocks' => $stats['blocks'] ?? 0,
                    'turnovers' => $stats['turnovers'] ?? 0,
                    'fouls' => $stats['fouls_personal'] ?? 0,
                ];
            }
        }

        return $records;
    }
}
