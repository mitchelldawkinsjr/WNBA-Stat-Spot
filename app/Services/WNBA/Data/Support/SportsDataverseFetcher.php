<?php

namespace App\Services\WNBA\Data\Support;

use Illuminate\Support\Facades\Http;
use League\Csv\Reader;

class SportsDataverseFetcher
{
    private int $dataSeasonYear;

    public function __construct()
    {
        $this->dataSeasonYear = (int) config('wnba.seasons.current_season');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function fetchPlayerBoxscores(int $season, array $options = []): array
    {
        $content = $this->download($this->playerBoxUrl($season));

        return $this->parsePlayerBoxCsv($content);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function fetchTeamBoxscores(int $season, array $options = []): array
    {
        $content = $this->download($this->teamBoxUrl($season));

        return $this->parseTeamBoxCsv($content);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchSchedule(int $season): array
    {
        $content = $this->download($this->scheduleUrl($season));

        return $this->parseScheduleCsv($content);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPlayByPlay(int $season): array
    {
        $content = $this->download($this->pbpUrl($season));

        return $this->parsePbpCsv($content);
    }

    /**
     * @return array<int, array{year: int, season: string}>
     */
    public function fetchAvailableSeasons(): array
    {
        return [
            [
                'year' => $this->dataSeasonYear,
                'season' => (string) config('wnba.seasons.current_season_label'),
            ],
        ];
    }

    private function playerBoxUrl(int $season): string
    {
        return config('wnba.data_source.feeds.player_boxscores')
            ?: "https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_player_boxscores/player_box_{$season}.csv";
    }

    private function teamBoxUrl(int $season): string
    {
        return config('wnba.data_source.feeds.team_boxscores')
            ?: "https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_team_boxscores/team_box_{$season}.csv";
    }

    private function scheduleUrl(int $season): string
    {
        return config('wnba.data_source.feeds.schedule')
            ?: "https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_schedules/wnba_schedule_{$season}.csv";
    }

    private function pbpUrl(int $season): string
    {
        return config('wnba.data_source.feeds.play_by_play')
            ?: "https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_pbp/play_by_play_{$season}.csv";
    }

    private function download(string $url): string
    {
        $response = Http::timeout((int) config('wnba.api.timeout', 30))->get($url);

        if (! $response->successful()) {
            throw new \Exception("Failed to download SportsDataverse feed from {$url}");
        }

        return $response->body();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parsePlayerBoxCsv(string $content): array
    {
        $csv = Reader::createFromString($content);
        $csv->setHeaderOffset(0);
        $records = [];

        foreach ($csv->getRecords() as $record) {
            $records[] = [
                'game_id' => $record['game_id'] ?? null,
                'season' => $record['season'] ?? $this->dataSeasonYear,
                'season_type' => $record['season_type'] ?? null,
                'game_date' => $record['game_date'] ?? null,
                'game_date_time' => $record['game_date_time'] ?? null,
                'athlete_id' => $record['athlete_id'] ?? null,
                'athlete_display_name' => $record['athlete_display_name'] ?? null,
                'team_id' => $record['team_id'] ?? null,
                'team_name' => $record['team_name'] ?? null,
                'team_location' => $record['team_location'] ?? null,
                'minutes' => $record['minutes'] ?? null,
                'field_goals_made' => (int) ($record['field_goals_made'] ?? 0),
                'field_goals_attempted' => (int) ($record['field_goals_attempted'] ?? 0),
                'three_point_field_goals_made' => (int) ($record['three_point_field_goals_made'] ?? 0),
                'three_point_field_goals_attempted' => (int) ($record['three_point_field_goals_attempted'] ?? 0),
                'free_throws_made' => (int) ($record['free_throws_made'] ?? 0),
                'free_throws_attempted' => (int) ($record['free_throws_attempted'] ?? 0),
                'offensive_rebounds' => (int) ($record['offensive_rebounds'] ?? 0),
                'defensive_rebounds' => (int) ($record['defensive_rebounds'] ?? 0),
                'rebounds' => (int) ($record['rebounds'] ?? 0),
                'assists' => (int) ($record['assists'] ?? 0),
                'steals' => (int) ($record['steals'] ?? 0),
                'blocks' => (int) ($record['blocks'] ?? 0),
                'turnovers' => (int) ($record['turnovers'] ?? 0),
                'fouls' => (int) ($record['fouls'] ?? 0),
                'plus_minus' => (int) ($record['plus_minus'] ?? 0),
                'points' => (int) ($record['points'] ?? 0),
                'starter' => ($record['starter'] ?? '') === 'TRUE',
                'active' => ($record['active'] ?? 'TRUE') === 'TRUE',
                'athlete_jersey' => $record['athlete_jersey'] ?? null,
                'athlete_short_name' => $record['athlete_short_name'] ?? null,
                'athlete_headshot_href' => $record['athlete_headshot_href'] ?? null,
                'athlete_position_name' => $record['athlete_position_name'] ?? null,
                'athlete_position_abbreviation' => $record['athlete_position_abbreviation'] ?? null,
                'team_display_name' => $record['team_display_name'] ?? null,
                'team_abbreviation' => $record['team_abbreviation'] ?? null,
                'home_away' => $record['home_away'] ?? null,
                'team_score' => $record['team_score'] ?? null,
                'opponent_team_id' => $record['opponent_team_id'] ?? null,
                'opponent_team_name' => $record['opponent_team_name'] ?? null,
                'opponent_team_display_name' => $record['opponent_team_display_name'] ?? null,
                'opponent_team_abbreviation' => $record['opponent_team_abbreviation'] ?? null,
                'opponent_team_score' => $record['opponent_team_score'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseTeamBoxCsv(string $content): array
    {
        $csv = Reader::createFromString($content);
        $csv->setHeaderOffset(0);
        $records = [];

        foreach ($csv->getRecords() as $record) {
            $records[] = [
                'game_id' => $record['game_id'] ?? null,
                'season' => $record['season'] ?? $this->dataSeasonYear,
                'season_type' => $record['season_type'] ?? null,
                'game_date' => $record['game_date'] ?? null,
                'game_date_time' => $record['game_date_time'] ?? null,
                'team_id' => $record['team_id'] ?? null,
                'team_name' => $record['team_name'] ?? null,
                'team_location' => $record['team_location'] ?? null,
                'team_abbreviation' => $record['team_abbreviation'] ?? null,
                'team_display_name' => $record['team_display_name'] ?? null,
                'home_away' => $record['team_home_away'] ?? null,
                'team_score' => $record['team_score'] ?? 0,
                'opponent_team_id' => $record['opponent_team_id'] ?? null,
                'opponent_team_name' => $record['opponent_team_name'] ?? null,
                'opponent_team_display_name' => $record['opponent_team_display_name'] ?? null,
                'opponent_team_abbreviation' => $record['opponent_team_abbreviation'] ?? null,
                'opponent_team_score' => $record['opponent_team_score'] ?? 0,
                'field_goals_made' => $record['field_goals_made'] ?? 0,
                'field_goals_attempted' => $record['field_goals_attempted'] ?? 0,
                'three_point_field_goals_made' => $record['three_point_field_goals_made'] ?? 0,
                'three_point_field_goals_attempted' => $record['three_point_field_goals_attempted'] ?? 0,
                'free_throws_made' => $record['free_throws_made'] ?? 0,
                'free_throws_attempted' => $record['free_throws_attempted'] ?? 0,
                'offensive_rebounds' => $record['offensive_rebounds'] ?? 0,
                'defensive_rebounds' => $record['defensive_rebounds'] ?? 0,
                'rebounds' => $record['rebounds'] ?? 0,
                'assists' => $record['assists'] ?? 0,
                'steals' => $record['steals'] ?? 0,
                'blocks' => $record['blocks'] ?? 0,
                'turnovers' => $record['turnovers'] ?? 0,
                'fouls' => $record['fouls'] ?? 0,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseScheduleCsv(string $content): array
    {
        $csv = Reader::createFromString($content);
        $csv->setHeaderOffset(0);
        $records = [];

        foreach ($csv->getRecords() as $record) {
            $records[] = [
                'game_id' => $record['game_id'] ?? null,
                'season' => $record['season'] ?? $this->dataSeasonYear,
                'season_type' => $record['season_type'] ?? null,
                'game_date' => $record['game_date'] ?? null,
                'game_date_time' => $record['game_date_time'] ?? null,
                'home_team_id' => $record['home_id'] ?? null,
                'home_team_name' => $record['home_name'] ?? null,
                'home_team_location' => $record['home_location'] ?? null,
                'home_team_abbreviation' => $record['home_abbreviation'] ?? null,
                'home_team_display_name' => $record['home_display_name'] ?? null,
                'away_team_id' => $record['away_id'] ?? null,
                'away_team_name' => $record['away_name'] ?? null,
                'away_team_location' => $record['away_location'] ?? null,
                'away_team_abbreviation' => $record['away_abbreviation'] ?? null,
                'away_team_display_name' => $record['away_display_name'] ?? null,
                'venue_name' => $record['venue_full_name'] ?? null,
                'venue_city' => $record['venue_address_city'] ?? null,
                'venue_state' => $record['venue_address_state'] ?? null,
                'status_name' => $record['status_type_name'] ?? null,
                'status_type' => $record['status_type_state'] ?? null,
                'status_abbreviation' => $record['status_type_abbreviation'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parsePbpCsv(string $content): array
    {
        $csv = Reader::createFromString($content);
        $csv->setHeaderOffset(0);
        $records = [];

        foreach ($csv->getRecords() as $record) {
            $records[] = $record;
        }

        return $records;
    }
}
