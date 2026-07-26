<?php

namespace Tests\Unit\Services;

use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\Providers\SportsDataverseWnbaProvider;
use App\Services\WnbaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WnbaDataServiceNullSafeIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_team_schedule_data_tolerates_missing_venue_and_logo_fields(): void
    {
        $service = new WnbaDataService(app(SportsDataverseWnbaProvider::class));

        $service->saveTeamScheduleData([
            [
                'game_id' => '401999001',
                'season' => 2026,
                'season_type' => 2,
                'game_date' => '2026-07-26',
                'game_date_time' => '2026-07-26 19:00:00',
                // intentionally omit venue_* and team logos
                'status_id' => '1',
                'status_name' => 'STATUS_SCHEDULED',
                'status_type' => 'scheduled',
                'status_abbreviation' => 'Sched',
                'home_team_id' => '17',
                'home_team_name' => 'Aces',
                'home_team_location' => 'Las Vegas',
                'home_team_abbreviation' => 'LV',
                'home_team_display_name' => 'Las Vegas Aces',
                'away_team_id' => '5',
                'away_team_name' => 'Dream',
                'away_team_location' => 'Atlanta',
                'away_team_abbreviation' => 'ATL',
                'away_team_display_name' => 'Atlanta Dream',
            ],
        ]);

        $game = WnbaGame::where('game_id', '401999001')->first();
        $this->assertNotNull($game);
        $this->assertNull($game->venue_id);
        $this->assertNull($game->venue_name);

        $home = WnbaTeam::where('team_id', '17')->first();
        $away = WnbaTeam::where('team_id', '5')->first();
        $this->assertNotNull($home);
        $this->assertNotNull($away);
        $this->assertNull($home->team_logo);
        $this->assertNull($away->team_logo);
        $this->assertSame(2, WnbaGameTeam::where('game_id', $game->id)->count());
    }

    public function test_save_team_data_tolerates_missing_team_logo_fields(): void
    {
        $service = new WnbaDataService(app(SportsDataverseWnbaProvider::class));

        $service->saveTeamData([
            [
                'game_id' => '401999002',
                'season' => 2026,
                'season_type' => 2,
                'game_date' => '2026-07-25',
                'game_date_time' => '2026-07-25 19:00:00',
                'team_id' => '17',
                'team_name' => 'Aces',
                'team_location' => 'Las Vegas',
                'team_abbreviation' => 'LV',
                'team_display_name' => 'Las Vegas Aces',
                // omit team_logo / team_color
                'opponent_team_id' => '5',
                'opponent_team_name' => 'Dream',
                'opponent_team_location' => 'Atlanta',
                'opponent_team_abbreviation' => 'ATL',
                'opponent_team_display_name' => 'Atlanta Dream',
                'home_away' => 'home',
                'team_winner' => true,
                'team_score' => 88,
                'opponent_team_score' => 80,
                'field_goals_made' => 30,
                'field_goals_attempted' => 70,
                'three_point_field_goals_made' => 8,
                'three_point_field_goals_attempted' => 25,
                'free_throws_made' => 20,
                'free_throws_attempted' => 24,
                'offensive_rebounds' => 10,
                'defensive_rebounds' => 25,
                'rebounds' => 35,
                'assists' => 20,
                'steals' => 8,
                'blocks' => 4,
                'turnovers' => 12,
                'fouls' => 16,
            ],
        ]);

        $team = WnbaTeam::where('team_id', '17')->first();
        $this->assertNotNull($team);
        $this->assertNull($team->team_logo);

        $game = WnbaGame::where('game_id', '401999002')->first();
        $this->assertNotNull($game);
        $this->assertSame(1, WnbaGameTeam::where('game_id', $game->id)->where('team_id', '17')->count());
    }
}
