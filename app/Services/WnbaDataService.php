<?php

namespace App\Services;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaPlay;
use App\Models\WnbaTeam;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class WnbaDataService
{
    private string $wnbaBoxScoreUrl;
    private string $wnbaTeamUrl;
    private string $wnbaPbpUrl;
    private string $wnbaTeamScheduleUrl;

    public function __construct()
    {
        $this->wnbaBoxScoreUrl = env('WNBA_BOX_SCORE_URL', 'https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_player_boxscores/player_box_2025.csv');
        $this->wnbaTeamUrl = env('WNBA_TEAM_URL', 'https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_team_boxscores/team_box_2025.csv');
        $this->wnbaPbpUrl = env('WNBA_PBP_URL', 'https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_pbp/play_by_play_2025.csv');
        $this->wnbaTeamScheduleUrl = env('WNBA_TEAM_SCHEDULE_URL', 'https://github.com/sportsdataverse/sportsdataverse-data/releases/download/espn_wnba_schedules/wnba_schedule_2025.csv');
    }

    /**
     * Safely get a value from an array with an optional default
     */
    private function getOptionalValue(array $record, string $key, mixed $default = null): mixed
    {
        return $record[$key] ?? $default;
    }

    /**
     * Safely get a boolean value from an array
     */
    private function getOptionalBool(array $record, string $key, bool $default = false): bool
    {
        $value = $this->getOptionalValue($record, $key);
        if ($value === null) {
            return $default;
        }
        return $value === 'TRUE' || $value === true || $value === '1' || $value === 1;
    }

    /**
     * Safely get an integer value from an array
     */
    private function getOptionalInt(array $record, string $key, int $default = 0): int
    {
        $value = $this->getOptionalValue($record, $key);
        if ($value === null || $value === '') {
            return $default;
        }
        return (int) $value;
    }

    public function downloadBoxScoreData(): string
    {
        $response = Http::get($this->wnbaBoxScoreUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to download WNBA box score data');
        }

        $csvContent = $response->body();

        // Save to storage
        $path = 'wnba/player_box_2025.csv';
        Storage::put($path, $csvContent);

        return $path;
    }

    public function downloadTeamData(): string
    {
        $response = Http::get($this->wnbaTeamUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to download WNBA team data');
        }

        $csvContent = $response->body();

        // Save to storage
        $path = 'wnba/team_box_2025.csv';
        Storage::put($path, $csvContent);

        return $path;
    }

    public function downloadTeamScheduleData(): string
    {
        $response = Http::get($this->wnbaTeamScheduleUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to download WNBA team schedule data');
        }

        $csvContent = $response->body();

        // Save to storage
        $path = 'wnba/team_schedule_2025.csv';
        Storage::put($path, $csvContent);

        return $path;
    }

    public function downloadPbpData(): string
    {
        $response = Http::get($this->wnbaPbpUrl);

        if (!$response->successful()) {
            throw new \Exception('Failed to download WNBA PBP data');
        }

        $csvContent = $response->body();

        // Save to storage
        $path = 'wnba/play_by_play_2025.csv';
        Storage::put($path, $csvContent);

        return $path;
    }

    public function parseBoxScoreData(string $path): array
    {
        $csvContent = Storage::get($path);

        $csv = Reader::createFromString($csvContent);
        $csv->setHeaderOffset(0);

        $records = [];
        foreach ($csv->getRecords() as $record) {
            $records[] = [
                'game_id' => $this->getOptionalValue($record, 'game_id'),
                'season' => $this->getOptionalValue($record, 'season'),
                'season_type' => $this->getOptionalValue($record, 'season_type'),
                'game_date' => $this->getOptionalValue($record, 'game_date'),
                'game_date_time' => $this->getOptionalValue($record, 'game_date_time'),
                'athlete_id' => $this->getOptionalValue($record, 'athlete_id'),
                'athlete_display_name' => $this->getOptionalValue($record, 'athlete_display_name'),
                'team_id' => $this->getOptionalValue($record, 'team_id'),
                'team_name' => $this->getOptionalValue($record, 'team_name'),
                'team_location' => $this->getOptionalValue($record, 'team_location'),
                'minutes' => $this->getOptionalValue($record, 'minutes'),
                'field_goals_made' => $this->getOptionalInt($record, 'field_goals_made'),
                'field_goals_attempted' => $this->getOptionalInt($record, 'field_goals_attempted'),
                'three_point_field_goals_made' => $this->getOptionalInt($record, 'three_point_field_goals_made'),
                'three_point_field_goals_attempted' => $this->getOptionalInt($record, 'three_point_field_goals_attempted'),
                'free_throws_made' => $this->getOptionalInt($record, 'free_throws_made'),
                'free_throws_attempted' => $this->getOptionalInt($record, 'free_throws_attempted'),
                'offensive_rebounds' => $this->getOptionalInt($record, 'offensive_rebounds'),
                'defensive_rebounds' => $this->getOptionalInt($record, 'defensive_rebounds'),
                'rebounds' => $this->getOptionalInt($record, 'rebounds'),
                'assists' => $this->getOptionalInt($record, 'assists'),
                'steals' => $this->getOptionalInt($record, 'steals'),
                'blocks' => $this->getOptionalInt($record, 'blocks'),
                'turnovers' => $this->getOptionalInt($record, 'turnovers'),
                'fouls' => $this->getOptionalInt($record, 'fouls'),
                'plus_minus' => $this->getOptionalInt($record, 'plus_minus'),
                'points' => $this->getOptionalInt($record, 'points'),
                'starter' => $this->getOptionalBool($record, 'starter'),
                'ejected' => $this->getOptionalBool($record, 'ejected'),
                'did_not_play' => $this->getOptionalBool($record, 'did_not_play'),
                'reason' => $this->getOptionalValue($record, 'reason'),
                'active' => $this->getOptionalBool($record, 'active'),
                'athlete_jersey' => $this->getOptionalValue($record, 'athlete_jersey'),
                'athlete_short_name' => $this->getOptionalValue($record, 'athlete_short_name'),
                'athlete_headshot_href' => $this->getOptionalValue($record, 'athlete_headshot_href'),
                'athlete_position_name' => $this->getOptionalValue($record, 'athlete_position_name'),
                'athlete_position_abbreviation' => $this->getOptionalValue($record, 'athlete_position_abbreviation'),
                'team_display_name' => $this->getOptionalValue($record, 'team_display_name'),
                'team_uid' => $this->getOptionalValue($record, 'team_uid'),
                'team_slug' => $this->getOptionalValue($record, 'team_slug'),
                'team_logo' => $this->getOptionalValue($record, 'team_logo'),
                'team_abbreviation' => $this->getOptionalValue($record, 'team_abbreviation'),
                'team_color' => $this->getOptionalValue($record, 'team_color'),
                'team_alternate_color' => $this->getOptionalValue($record, 'team_alternate_color'),
                'home_away' => $this->getOptionalValue($record, 'home_away'),
                'team_winner' => $this->getOptionalBool($record, 'team_winner'),
                'team_score' => $this->getOptionalValue($record, 'team_score'),
                'opponent_team_id' => $this->getOptionalValue($record, 'opponent_team_id'),
                'opponent_team_name' => $this->getOptionalValue($record, 'opponent_team_name'),
                'opponent_team_location' => $this->getOptionalValue($record, 'opponent_team_location'),
                'opponent_team_display_name' => $this->getOptionalValue($record, 'opponent_team_display_name'),
                'opponent_team_abbreviation' => $this->getOptionalValue($record, 'opponent_team_abbreviation'),
                'opponent_team_logo' => $this->getOptionalValue($record, 'opponent_team_logo'),
                'opponent_team_color' => $this->getOptionalValue($record, 'opponent_team_color'),
                'opponent_team_alternate_color' => $this->getOptionalValue($record, 'opponent_team_alternate_color'),
                'opponent_team_score' => $this->getOptionalValue($record, 'opponent_team_score'),
            ];
        }

        return $records;
    }

    public function parseTeamData(string $path): array
    {
        $csvContent = Storage::get($path);

        $csv = Reader::createFromString($csvContent);
        $csv->setHeaderOffset(0);

        $records = [];
        foreach ($csv->getRecords() as $record) {
            $records[] = [
                'game_id' => $this->getOptionalValue($record, 'game_id'),
                'season' => $this->getOptionalValue($record, 'season'),
                'season_type' => $this->getOptionalValue($record, 'season_type'),
                'game_date' => $this->getOptionalValue($record, 'game_date'),
                'game_date_time' => $this->getOptionalValue($record, 'game_date_time'),
                'team_id' => $this->getOptionalValue($record, 'team_id'),
                'team_name' => $this->getOptionalValue($record, 'team_name'),
                'team_location' => $this->getOptionalValue($record, 'team_location'),
                'team_abbreviation' => $this->getOptionalValue($record, 'team_abbreviation'),
                'team_display_name' => $this->getOptionalValue($record, 'team_display_name'),
                'team_uid' => $this->getOptionalValue($record, 'team_uid'),
                'team_slug' => $this->getOptionalValue($record, 'team_slug'),
                'team_logo' => $this->getOptionalValue($record, 'team_logo'),
                'team_color' => $this->getOptionalValue($record, 'team_color'),
                'team_alternate_color' => $this->getOptionalValue($record, 'team_alternate_color'),
                'home_away' => $this->getOptionalValue($record, 'team_home_away'),
                'team_winner' => $this->getOptionalBool($record, 'team_winner'),
                'team_score' => $this->getOptionalValue($record, 'team_score'),
                'opponent_team_id' => $this->getOptionalValue($record, 'opponent_team_id'),
                'opponent_team_name' => $this->getOptionalValue($record, 'opponent_team_name'),
                'opponent_team_location' => $this->getOptionalValue($record, 'opponent_team_location'),
                'opponent_team_display_name' => $this->getOptionalValue($record, 'opponent_team_display_name'),
                'opponent_team_abbreviation' => $this->getOptionalValue($record, 'opponent_team_abbreviation'),
                'opponent_team_logo' => $this->getOptionalValue($record, 'opponent_team_logo'),
                'opponent_team_color' => $this->getOptionalValue($record, 'opponent_team_color'),
                'opponent_team_alternate_color' => $this->getOptionalValue($record, 'opponent_team_alternate_color'),
                'opponent_team_score' => $this->getOptionalValue($record, 'opponent_team_score'),
                'field_goals_made' => $this->getOptionalValue($record, 'field_goals_made'),
                'field_goals_attempted' => $this->getOptionalValue($record, 'field_goals_attempted'),
                'three_point_field_goals_made' => $this->getOptionalValue($record, 'three_point_field_goals_made'),
                'three_point_field_goals_attempted' => $this->getOptionalValue($record, 'three_point_field_goals_attempted'),
                'free_throws_made' => $this->getOptionalValue($record, 'free_throws_made'),
                'free_throws_attempted' => $this->getOptionalValue($record, 'free_throws_attempted'),
                'offensive_rebounds' => $this->getOptionalValue($record, 'offensive_rebounds'),
                'defensive_rebounds' => $this->getOptionalValue($record, 'defensive_rebounds'),
                'rebounds' => $this->getOptionalValue($record, 'rebounds'),
                'assists' => $this->getOptionalValue($record, 'assists'),
                'steals' => $this->getOptionalValue($record, 'steals'),
                'blocks' => $this->getOptionalValue($record, 'blocks'),
                'turnovers' => $this->getOptionalValue($record, 'turnovers'),
                'fouls' => $this->getOptionalValue($record, 'fouls'),
            ];
        }

        return $records;
    }

    public function parseTeamScheduleData(string $path): array
    {
        $csvContent = Storage::get($path);

        $csv = Reader::createFromString($csvContent);
        $csv->setHeaderOffset(0);

        $records = [];
        foreach ($csv->getRecords() as $record) {
            $records[] = [
                'game_id' => $this->getOptionalValue($record, 'game_id'),
                'season' => $this->getOptionalValue($record, 'season'),
                'season_type' => $this->getOptionalValue($record, 'season_type'),
                'game_date' => $this->getOptionalValue($record, 'game_date'),
                'game_date_time' => $this->getOptionalValue($record, 'game_date_time'),
                'home_team_id' => $this->getOptionalValue($record, 'home_id'),
                'home_team_name' => $this->getOptionalValue($record, 'home_name'),
                'home_team_location' => $this->getOptionalValue($record, 'home_location'),
                'home_team_abbreviation' => $this->getOptionalValue($record, 'home_abbreviation'),
                'home_team_display_name' => $this->getOptionalValue($record, 'home_display_name'),
                'home_team_uid' => $this->getOptionalValue($record, 'home_uid'),
                'home_team_slug' => $this->getOptionalValue($record, 'home_slug'),
                'home_team_logo' => $this->getOptionalValue($record, 'home_logo'),
                'home_team_color' => $this->getOptionalValue($record, 'home_color'),
                'home_team_alternate_color' => $this->getOptionalValue($record, 'home_alternate_color'),
                'away_team_id' => $this->getOptionalValue($record, 'away_id'),
                'away_team_name' => $this->getOptionalValue($record, 'away_name'),
                'away_team_location' => $this->getOptionalValue($record, 'away_location'),
                'away_team_abbreviation' => $this->getOptionalValue($record, 'away_abbreviation'),
                'away_team_display_name' => $this->getOptionalValue($record, 'away_display_name'),
                'away_team_uid' => $this->getOptionalValue($record, 'away_uid'),
                'away_team_slug' => $this->getOptionalValue($record, 'away_slug'),
                'away_team_logo' => $this->getOptionalValue($record, 'away_logo'),
                'away_team_color' => $this->getOptionalValue($record, 'away_color'),
                'away_team_alternate_color' => $this->getOptionalValue($record, 'away_alternate_color'),
                'venue_id' => $this->getOptionalValue($record, 'venue_id'),
                'venue_name' => $this->getOptionalValue($record, 'venue_full_name'),
                'venue_city' => $this->getOptionalValue($record, 'venue_address_city'),
                'venue_state' => $this->getOptionalValue($record, 'venue_address_state'),
                'venue_country' => $this->getOptionalValue($record, 'venue_country'),
                'venue_capacity' => $this->getOptionalValue($record, 'venue_capacity'),
                'venue_surface' => $this->getOptionalValue($record, 'venue_surface'),
                'venue_indoor' => $this->getOptionalBool($record, 'venue_indoor'),
                'status_id' => $this->getOptionalValue($record, 'status_type_id'),
                'status_name' => $this->getOptionalValue($record, 'status_type_name'),
                'status_type' => $this->getOptionalValue($record, 'status_type_state'),
                'status_abbreviation' => $this->getOptionalValue($record, 'status_type_abbreviation'),
            ];
        }

        return $records;
    }

    public function parsePbpData(string $path): array
    {
        $csvContent = Storage::get($path);

        $csv = Reader::createFromString($csvContent);
        $csv->setHeaderOffset(0);

        $records = [];
        foreach ($csv->getRecords() as $record) {
            $records[] = [
                'game_id' => $this->getOptionalValue($record, 'game_id'),
                'season' => $this->getOptionalValue($record, 'season'),
                'season_type' => $this->getOptionalValue($record, 'season_type'),
                'game_date' => $this->getOptionalValue($record, 'game_date'),
                'game_date_time' => $this->getOptionalValue($record, 'game_date_time'),
                'period' => $this->getOptionalValue($record, 'period'),
                'period_display_value' => $this->getOptionalValue($record, 'period_display_value'),
                'clock_display_value' => $this->getOptionalValue($record, 'clock_display_value'),
                'team_id' => $this->getOptionalValue($record, 'team_id'),
                'team_name' => $this->getOptionalValue($record, 'team_name'),
                'team_location' => $this->getOptionalValue($record, 'team_location'),
                'team_abbreviation' => $this->getOptionalValue($record, 'team_abbreviation'),
                'team_display_name' => $this->getOptionalValue($record, 'team_display_name'),
                'team_uid' => $this->getOptionalValue($record, 'team_uid'),
                'team_slug' => $this->getOptionalValue($record, 'team_slug'),
                'team_logo' => $this->getOptionalValue($record, 'team_logo'),
                'team_color' => $this->getOptionalValue($record, 'team_color'),
                'team_alternate_color' => $this->getOptionalValue($record, 'team_alternate_color'),
                'home_away' => $this->getOptionalValue($record, 'home_away'),
                'team_winner' => $this->getOptionalBool($record, 'team_winner'),
                'team_score' => $this->getOptionalValue($record, 'team_score'),
                'opponent_team_id' => $this->getOptionalValue($record, 'opponent_team_id'),
                'opponent_team_name' => $this->getOptionalValue($record, 'opponent_team_name'),
                'opponent_team_location' => $this->getOptionalValue($record, 'opponent_team_location'),
                'opponent_team_display_name' => $this->getOptionalValue($record, 'opponent_team_display_name'),
                'opponent_team_abbreviation' => $this->getOptionalValue($record, 'opponent_team_abbreviation'),
                'opponent_team_logo' => $this->getOptionalValue($record, 'opponent_team_logo'),
                'opponent_team_color' => $this->getOptionalValue($record, 'opponent_team_color'),
                'opponent_team_alternate_color' => $this->getOptionalValue($record, 'opponent_team_alternate_color'),
                'opponent_team_score' => $this->getOptionalValue($record, 'opponent_team_score'),
                'play_id' => $this->getOptionalValue($record, 'play_id'),
                'play_sequence_number' => $this->getOptionalValue($record, 'play_sequence_number'),
                'play_type_id' => $this->getOptionalValue($record, 'play_type_id'),
                'play_type_text' => $this->getOptionalValue($record, 'play_type_text'),
                'play_type_abbreviation' => $this->getOptionalValue($record, 'play_type_abbreviation'),
                'play_text' => $this->getOptionalValue($record, 'play_text'),
                'athlete_id' => $this->getOptionalValue($record, 'athlete_id'),
                'athlete_display_name' => $this->getOptionalValue($record, 'athlete_display_name'),
                'athlete_jersey' => $this->getOptionalValue($record, 'athlete_jersey'),
                'athlete_short_name' => $this->getOptionalValue($record, 'athlete_short_name'),
                'athlete_headshot_href' => $this->getOptionalValue($record, 'athlete_headshot_href'),
                'athlete_position_name' => $this->getOptionalValue($record, 'athlete_position_name'),
                'athlete_position_abbreviation' => $this->getOptionalValue($record, 'athlete_position_abbreviation'),
                'score_value' => $this->getOptionalValue($record, 'score_value'),
                'score_team_id' => $this->getOptionalValue($record, 'score_team_id'),
                'score_team_name' => $this->getOptionalValue($record, 'score_team_name'),
                'score_team_location' => $this->getOptionalValue($record, 'score_team_location'),
                'score_team_abbreviation' => $this->getOptionalValue($record, 'score_team_abbreviation'),
                'score_team_display_name' => $this->getOptionalValue($record, 'score_team_display_name'),
                'score_team_uid' => $this->getOptionalValue($record, 'score_team_uid'),
                'score_team_slug' => $this->getOptionalValue($record, 'score_team_slug'),
                'score_team_logo' => $this->getOptionalValue($record, 'score_team_logo'),
                'score_team_color' => $this->getOptionalValue($record, 'score_team_color'),
                'score_team_alternate_color' => $this->getOptionalValue($record, 'score_team_alternate_color'),
            ];
        }

        return $records;
    }

    public function saveBoxScoreData(array $records): void
    {
        foreach ($records as $record) {
            // Skip records with missing required fields
            if (empty($record['game_id']) || empty($record['team_id']) || empty($record['athlete_id'])) {
                continue;
            }

            // Create or update game
            $game = WnbaGame::updateOrCreate(
                ['game_id' => $record['game_id']],
                [
                    'season' => $record['season'],
                    'season_type' => $record['season_type'],
                    'game_date' => $record['game_date'],
                    'game_date_time' => $record['game_date_time'],
                ]
            );

            // Create or update team
            $team = WnbaTeam::updateOrCreate(
                ['team_id' => $record['team_id']],
                [
                    'team_name' => $record['team_name'] ?? 'Unknown Team',
                    'team_location' => $record['team_location'] ?? 'Unknown',
                    'team_abbreviation' => $record['team_abbreviation'] ?? 'UNK',
                    'team_display_name' => $record['team_display_name'] ?? 'Unknown Team',
                    'team_uid' => $record['team_uid'] ?? null,
                    'team_slug' => $record['team_slug'] ?? null,
                    'team_logo' => $record['team_logo'] ?? null,
                    'team_color' => $record['team_color'] ?? null,
                    'team_alternate_color' => $record['team_alternate_color'] ?? null,
                ]
            );

            // Create or update opponent team (only if opponent_team_id exists)
            $opponentTeam = null;
            if (!empty($record['opponent_team_id'])) {
                $opponentTeam = WnbaTeam::updateOrCreate(
                    ['team_id' => $record['opponent_team_id']],
                    [
                        'team_name' => $record['opponent_team_name'] ?? 'Unknown Team',
                        'team_location' => $record['opponent_team_location'] ?? 'Unknown',
                        'team_abbreviation' => $record['opponent_team_abbreviation'] ?? 'UNK',
                        'team_display_name' => $record['opponent_team_display_name'] ?? 'Unknown Team',
                        'team_uid' => $record['opponent_team_uid'] ?? null,
                        'team_slug' => $record['opponent_team_slug'] ?? null,
                        'team_logo' => $record['opponent_team_logo'] ?? null,
                        'team_color' => $record['opponent_team_color'] ?? null,
                        'team_alternate_color' => $record['opponent_team_alternate_color'] ?? null,
                    ]
                );
            }

            // Create or update player
            $player = WnbaPlayer::updateOrCreate(
                ['athlete_id' => $record['athlete_id']],
                [
                    'athlete_display_name' => $record['athlete_display_name'] ?? 'Unknown Player',
                    'athlete_short_name' => $record['athlete_short_name'] ?? 'Unknown',
                    'athlete_jersey' => $record['athlete_jersey'] ?? null,
                    'athlete_headshot_href' => $record['athlete_headshot_href'] ?? null,
                    'athlete_position_name' => $record['athlete_position_name'] ?? null,
                    'athlete_position_abbreviation' => $record['athlete_position_abbreviation'] ?? null,
                ]
            );

            // Create or update player game
            WnbaPlayerGame::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'player_id' => $player->id,
                ],
                [
                    'team_id' => $team->team_id,
                    'minutes' => $record['minutes'] ?? 0,
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
                    'plus_minus' => $record['plus_minus'] ?? 0,
                    'points' => $record['points'] ?? 0,
                    'starter' => $record['starter'] ?? false,
                    'ejected' => $record['ejected'] ?? false,
                    'did_not_play' => $record['did_not_play'] ?? false,
                    'reason' => $record['reason'] ?? null,
                    'active' => $record['active'] ?? true,
                ]
            );
        }
    }

    public function saveTeamData(array $records): void
    {
        foreach ($records as $record) {
            // Skip records with missing required fields
            if (empty($record['game_id']) || empty($record['team_id']) || empty($record['opponent_team_id'])) {
                continue;
            }

            // Skip records with invalid home_away values
            if (empty($record['home_away']) || !in_array($record['home_away'], ['home', 'away'])) {
                continue;
            }

            // Create or update game
            $game = WnbaGame::updateOrCreate(
                ['game_id' => $record['game_id']],
                [
                    'season' => $record['season'],
                    'season_type' => $record['season_type'],
                    'game_date' => $record['game_date'],
                    'game_date_time' => $record['game_date_time'],
                ]
            );

            // Create or update team
            $team = WnbaTeam::updateOrCreate(
                ['team_id' => $record['team_id']],
                [
                    'team_name' => $record['team_name'],
                    'team_location' => $record['team_location'],
                    'team_abbreviation' => $record['team_abbreviation'],
                    'team_display_name' => $record['team_display_name'],
                    'team_uid' => $record['team_uid'] ?? null,
                    'team_slug' => $record['team_slug'] ?? null,
                    'team_logo' => $record['team_logo'],
                    'team_color' => $record['team_color'],
                    'team_alternate_color' => $record['team_alternate_color'],
                ]
            );

            // Create or update opponent team
            $opponentTeam = WnbaTeam::updateOrCreate(
                ['team_id' => $record['opponent_team_id']],
                [
                    'team_name' => $record['opponent_team_name'],
                    'team_location' => $record['opponent_team_location'],
                    'team_abbreviation' => $record['opponent_team_abbreviation'],
                    'team_display_name' => $record['opponent_team_display_name'],
                    'team_uid' => $record['opponent_team_uid'] ?? null,
                    'team_slug' => $record['opponent_team_slug'] ?? null,
                    'team_logo' => $record['opponent_team_logo'],
                    'team_color' => $record['opponent_team_color'],
                    'team_alternate_color' => $record['opponent_team_alternate_color'],
                ]
            );

            // Create or update game team
            WnbaGameTeam::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'team_id' => $team->team_id,
                ],
                [
                    'opponent_team_id' => $opponentTeam->team_id,
                    'home_away' => $record['home_away'],
                    'team_winner' => $record['team_winner'] ?? false,
                    'team_score' => $record['team_score'] ?? 0,
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
                ]
            );
        }
    }

    public function saveTeamScheduleData(array $records): void
    {
        foreach ($records as $record) {
            // Create or update game
            $game = WnbaGame::updateOrCreate(
                ['game_id' => $record['game_id']],
                [
                    'season' => $record['season'],
                    'season_type' => $record['season_type'],
                    'game_date' => $record['game_date'],
                    'game_date_time' => $record['game_date_time'],
                    'venue_id' => $record['venue_id'],
                    'venue_name' => $record['venue_name'],
                    'venue_city' => $record['venue_city'],
                    'venue_state' => $record['venue_state'],
                    'venue_country' => $record['venue_country'],
                    'venue_capacity' => $record['venue_capacity'],
                    'venue_surface' => $record['venue_surface'],
                    'venue_indoor' => $record['venue_indoor'],
                    'status_id' => $record['status_id'],
                    'status_name' => $record['status_name'],
                    'status_type' => $record['status_type'],
                    'status_abbreviation' => $record['status_abbreviation'],
                ]
            );

            $homeTeam = null;
            $awayTeam = null;

            // Create or update home team (only if home_team_id is not null)
            if (!empty($record['home_team_id'])) {
                $homeTeam = WnbaTeam::updateOrCreate(
                    ['team_id' => $record['home_team_id']],
                    [
                        'team_name' => $record['home_team_name'] ?? 'Unknown',
                        'team_location' => $record['home_team_location'] ?? 'Unknown',
                        'team_abbreviation' => $record['home_team_abbreviation'] ?? 'UNK',
                        'team_display_name' => $record['home_team_display_name'] ?? 'Unknown Team',
                        'team_uid' => $record['home_team_uid'],
                        'team_slug' => $record['home_team_slug'],
                        'team_logo' => $record['home_team_logo'],
                        'team_color' => $record['home_team_color'],
                        'team_alternate_color' => $record['home_team_alternate_color'],
                    ]
                );
            }

            // Create or update away team (only if away_team_id is not null)
            if (!empty($record['away_team_id'])) {
                $awayTeam = WnbaTeam::updateOrCreate(
                    ['team_id' => $record['away_team_id']],
                    [
                        'team_name' => $record['away_team_name'] ?? 'Unknown',
                        'team_location' => $record['away_team_location'] ?? 'Unknown',
                        'team_abbreviation' => $record['away_team_abbreviation'] ?? 'UNK',
                        'team_display_name' => $record['away_team_display_name'] ?? 'Unknown Team',
                        'team_uid' => $record['away_team_uid'],
                        'team_slug' => $record['away_team_slug'],
                        'team_logo' => $record['away_team_logo'],
                        'team_color' => $record['away_team_color'],
                        'team_alternate_color' => $record['away_team_alternate_color'],
                    ]
                );
            }

            // Create WnbaGameTeam records to associate teams with games
            if ($homeTeam && $awayTeam) {
                // Create home team association
                WnbaGameTeam::updateOrCreate(
                    [
                        'game_id' => $game->id,
                        'team_id' => $homeTeam->team_id,
                    ],
                    [
                        'opponent_team_id' => $awayTeam->team_id,
                        'home_away' => 'home',
                        'team_winner' => false, // Default, will be updated when game is completed
                        'team_score' => 0, // Default, will be updated when game is completed
                        'opponent_team_score' => 0, // Default, will be updated when game is completed
                        'field_goals_made' => 0,
                        'field_goals_attempted' => 0,
                        'three_point_field_goals_made' => 0,
                        'three_point_field_goals_attempted' => 0,
                        'free_throws_made' => 0,
                        'free_throws_attempted' => 0,
                        'offensive_rebounds' => 0,
                        'defensive_rebounds' => 0,
                        'rebounds' => 0,
                        'assists' => 0,
                        'steals' => 0,
                        'blocks' => 0,
                        'turnovers' => 0,
                        'fouls' => 0,
                    ]
                );

                // Create away team association
                WnbaGameTeam::updateOrCreate(
                    [
                        'game_id' => $game->id,
                        'team_id' => $awayTeam->team_id,
                    ],
                    [
                        'opponent_team_id' => $homeTeam->team_id,
                        'home_away' => 'away',
                        'team_winner' => false, // Default, will be updated when game is completed
                        'team_score' => 0, // Default, will be updated when game is completed
                        'opponent_team_score' => 0, // Default, will be updated when game is completed
                        'field_goals_made' => 0,
                        'field_goals_attempted' => 0,
                        'three_point_field_goals_made' => 0,
                        'three_point_field_goals_attempted' => 0,
                        'free_throws_made' => 0,
                        'free_throws_attempted' => 0,
                        'offensive_rebounds' => 0,
                        'defensive_rebounds' => 0,
                        'rebounds' => 0,
                        'assists' => 0,
                        'steals' => 0,
                        'blocks' => 0,
                        'turnovers' => 0,
                        'fouls' => 0,
                    ]
                );
            }
        }
    }

    public function savePbpData(array $records): void
    {
        foreach ($records as $record) {
            // Create or update game
            $game = WnbaGame::updateOrCreate(
                ['game_id' => $record['game_id']],
                [
                    'season' => $record['season'],
                    'season_type' => $record['season_type'],
                    'game_date' => $record['game_date'],
                    'game_date_time' => $record['game_date_time'],
                ]
            );

            // Create or update team
            $team = WnbaTeam::updateOrCreate(
                ['team_id' => $record['team_id']],
                [
                    'team_name' => $record['team_name'],
                    'team_location' => $record['team_location'],
                    'team_abbreviation' => $record['team_abbreviation'],
                    'team_display_name' => $record['team_display_name'],
                    'team_uid' => $record['team_uid'],
                    'team_slug' => $record['team_slug'],
                    'team_logo' => $record['team_logo'],
                    'team_color' => $record['team_color'],
                    'team_alternate_color' => $record['team_alternate_color'],
                ]
            );

            // Create or update player if exists
            $player = null;
            if (!empty($record['athlete_id'])) {
                $player = WnbaPlayer::updateOrCreate(
                    ['athlete_id' => $record['athlete_id']],
                    [
                        'athlete_display_name' => $record['athlete_display_name'],
                        'athlete_short_name' => $record['athlete_short_name'],
                        'athlete_jersey' => $record['athlete_jersey'],
                        'athlete_headshot_href' => $record['athlete_headshot_href'],
                        'athlete_position_name' => $record['athlete_position_name'],
                        'athlete_position_abbreviation' => $record['athlete_position_abbreviation'],
                    ]
                );
            }

            // Create or update score team if exists
            $scoreTeam = null;
            if (!empty($record['score_team_id'])) {
                $scoreTeam = WnbaTeam::updateOrCreate(
                    ['team_id' => $record['score_team_id']],
                    [
                        'team_name' => $record['score_team_name'],
                        'team_location' => $record['score_team_location'],
                        'team_abbreviation' => $record['score_team_abbreviation'],
                        'team_display_name' => $record['score_team_display_name'],
                        'team_uid' => $record['score_team_uid'],
                        'team_slug' => $record['score_team_slug'],
                        'team_logo' => $record['score_team_logo'],
                        'team_color' => $record['score_team_color'],
                        'team_alternate_color' => $record['score_team_alternate_color'],
                    ]
                );
            }

            // Create or update play
            WnbaPlay::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'play_id' => $record['play_id'],
                ],
                [
                    'play_sequence_number' => $record['play_sequence_number'],
                    'period' => $record['period'],
                    'period_display_value' => $record['period_display_value'],
                    'clock_display_value' => $record['clock_display_value'],
                    'team_id' => $team->team_id,
                    'player_id' => $player?->id,
                    'play_type_id' => $record['play_type_id'],
                    'play_type_text' => $record['play_type_text'],
                    'play_type_abbreviation' => $record['play_type_abbreviation'],
                    'play_text' => $record['play_text'],
                    'score_value' => $record['score_value'],
                    'score_team_id' => $scoreTeam?->team_id,
                ]
            );
        }
    }
}
