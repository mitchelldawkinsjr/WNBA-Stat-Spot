<?php

namespace Tests\Unit\Models;

use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WnbaPlayerGameTeamRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_relation_joins_external_team_id_not_primary_key(): void
    {
        // PK 17 would be wrong if BelongsTo matched id — Aces ESPN id is "17".
        WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
        ]);

        // Insert enough teams so someone gets primary key 17.
        for ($i = 2; $i <= 17; $i++) {
            WnbaTeam::create([
                'team_id' => (string) (100000 + $i),
                'team_name' => "Team {$i}",
                'team_location' => 'City',
                'team_abbreviation' => 'T'.$i,
                'team_display_name' => "City Team {$i}",
            ]);
        }

        $this->assertSame(17, (int) WnbaTeam::where('team_id', '100017')->value('id'));
        $this->assertSame('T17', WnbaTeam::find(17)?->team_abbreviation);

        $player = WnbaPlayer::create([
            'athlete_id' => '3149391',
            'athlete_display_name' => "A'ja Wilson",
            'athlete_short_name' => 'A. Wilson',
        ]);

        $game = WnbaGame::create([
            'game_id' => '401001',
            'season' => 2026,
            'season_type' => 2,
            'game_date' => '2026-06-01',
            'game_date_time' => '2026-06-01 19:00:00',
        ]);

        $playerGame = WnbaPlayerGame::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'team_id' => '17',
            'minutes' => '30:00',
            'points' => 20,
            'rebounds' => 10,
            'assists' => 3,
            'field_goals_made' => 8,
            'field_goals_attempted' => 15,
            'three_point_field_goals_made' => 0,
            'three_point_field_goals_attempted' => 1,
            'free_throws_made' => 4,
            'free_throws_attempted' => 4,
            'offensive_rebounds' => 2,
            'defensive_rebounds' => 8,
            'steals' => 1,
            'blocks' => 2,
            'turnovers' => 2,
            'fouls' => 2,
            'plus_minus' => 5,
            'starter' => true,
            'ejected' => false,
            'did_not_play' => false,
            'active' => true,
        ]);

        $playerGame->load('team');

        $this->assertSame('LV', $playerGame->team?->team_abbreviation);
        $this->assertSame('Las Vegas Aces', $playerGame->team?->team_display_name);
        $this->assertNotSame('T17', $playerGame->team?->team_abbreviation);
    }
}
