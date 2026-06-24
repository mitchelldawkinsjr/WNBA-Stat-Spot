<?php

namespace Tests\Unit\WNBA;

use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\EntityMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizes_tank01_box_score_to_canonical_espn_ids(): void
    {
        WnbaTeam::create([
            'team_id' => '20',
            'espn_team_id' => '20',
            'tank01_team_id' => '6',
            'team_name' => 'Fever',
            'team_location' => 'Indiana',
            'team_abbreviation' => 'IND',
            'team_display_name' => 'Indiana Fever',
        ]);

        WnbaPlayer::create([
            'athlete_id' => '4433402',
            'espn_athlete_id' => '4433402',
            'tank01_player_id' => '1004',
            'athlete_display_name' => 'Sydney Colson',
            'athlete_short_name' => 'S. Colson',
        ]);

        WnbaGame::create([
            'game_id' => '401857013',
            'espn_game_id' => '401857013',
            'tank01_game_id' => '20250807_IND@PHX',
            'season' => 2025,
            'season_type' => 2,
            'game_date' => '2025-08-07',
            'game_date_time' => '2025-08-07 19:00:00',
        ]);

        $merge = app(EntityMergeService::class);
        $normalized = $merge->normalizeBoxScoreRecord([
            'game_id' => '20250807_IND@PHX',
            'team_id' => '6',
            'athlete_id' => '1004',
            'season' => 2025,
            'season_type' => 2,
            'game_date' => '2025-08-07',
            'game_date_time' => '2025-08-07 19:00:00',
        ], 'tank01');

        $this->assertSame('401857013', $normalized['game_id']);
        $this->assertSame('20', $normalized['team_id']);
        $this->assertSame('4433402', $normalized['athlete_id']);
    }

    public function test_find_by_external_id_matches_provider_columns(): void
    {
        WnbaPlayer::create([
            'athlete_id' => '4433402',
            'espn_athlete_id' => '4433402',
            'tank01_player_id' => '1004',
            'athlete_display_name' => 'Sydney Colson',
            'athlete_short_name' => 'S. Colson',
        ]);

        $this->assertNotNull(WnbaPlayer::findByExternalId('1004'));
        $this->assertNotNull(WnbaPlayer::findByExternalId('4433402'));
    }
}
