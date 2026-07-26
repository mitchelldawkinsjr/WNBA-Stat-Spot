<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Models\WnbaDataConflict;
use App\Models\WnbaPlayer;
use App\Models\WnbaTeam;
use App\Services\WNBA\Agents\EntityIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityIntegrityServiceTest extends TestCase
{
    use RefreshDatabase;

    private EntityIntegrityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EntityIntegrityService;
    }

    public function test_duplicate_player_names_are_flagged_for_review(): void
    {
        WnbaPlayer::create([
            'athlete_id' => '1234567',
            'athlete_display_name' => 'Jane Doe',
            'athlete_short_name' => 'J. Doe',
        ]);
        WnbaPlayer::create([
            'athlete_id' => '7654321',
            'athlete_display_name' => 'Jane Doe',
            'athlete_short_name' => 'J. Doe',
        ]);

        $findings = $this->service->audit();

        $this->assertSame(1, $findings['duplicate_players']);

        $conflict = WnbaDataConflict::where('field', 'possible_duplicate')->first();
        $this->assertNotNull($conflict);
        $this->assertSame('player', $conflict->entity_type);
        $this->assertTrue((bool) $conflict->requires_review);
    }

    public function test_placeholder_team_abbreviation_is_flagged(): void
    {
        WnbaTeam::create([
            'team_id' => '99',
            'team_name' => 'Unknown Team',
            'team_location' => 'Unknown',
            'team_abbreviation' => 'UNK',
            'team_display_name' => 'Unknown Team',
        ]);

        $this->service->audit();

        $this->assertTrue(
            WnbaDataConflict::where('entity_type', 'team')
                ->where('field', 'placeholder_metadata')
                ->where('entity_key', '99')
                ->exists()
        );
    }

    public function test_repeated_audits_do_not_duplicate_open_findings(): void
    {
        WnbaPlayer::create([
            'athlete_id' => '1234567',
            'athlete_display_name' => 'Jane Doe',
            'athlete_short_name' => 'J. Doe',
        ]);
        WnbaPlayer::create([
            'athlete_id' => '7654321',
            'athlete_display_name' => 'Jane Doe',
            'athlete_short_name' => 'J. Doe',
        ]);

        $this->service->audit();
        $this->service->audit();

        $this->assertSame(1, WnbaDataConflict::where('field', 'possible_duplicate')->count());
    }

    public function test_clean_database_produces_no_findings(): void
    {
        WnbaPlayer::create([
            'athlete_id' => '1234567',
            'athlete_display_name' => 'Jane Doe',
            'athlete_short_name' => 'J. Doe',
        ]);
        WnbaTeam::create([
            'team_id' => '5',
            'team_name' => 'Dream',
            'team_location' => 'Atlanta',
            'team_abbreviation' => 'ATL',
            'team_display_name' => 'Atlanta Dream',
        ]);

        $findings = $this->service->audit();

        $this->assertSame(0, array_sum($findings));
        $this->assertSame(0, WnbaDataConflict::count());
    }

    public function test_mapping_gaps_split_espn_vs_tank01(): void
    {
        $team = WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
        ]);

        $game = \App\Models\WnbaGame::create([
            'game_id' => '401857999',
            'season' => 2026,
            'season_type' => 2,
            'game_date' => '2026-07-20',
            'game_date_time' => '2026-07-20 19:00:00',
        ]);

        // Missing ESPN mapping only
        $espnGap = WnbaPlayer::create([
            'athlete_id' => '9000001',
            'espn_athlete_id' => null,
            'tank01_player_id' => 'tank-1',
            'athlete_display_name' => 'Espn Gap',
            'athlete_short_name' => 'E. Gap',
        ]);

        // Missing Tank01 mapping only
        $tankGap = WnbaPlayer::create([
            'athlete_id' => '9000002',
            'espn_athlete_id' => '9000002',
            'tank01_player_id' => null,
            'athlete_display_name' => 'Tank Gap',
            'athlete_short_name' => 'T. Gap',
        ]);

        foreach ([$espnGap, $tankGap] as $player) {
            \App\Models\WnbaPlayerGame::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'team_id' => $team->team_id,
                'points' => 10,
                'rebounds' => 2,
                'assists' => 1,
                'field_goals_made' => 4,
                'field_goals_attempted' => 8,
                'three_point_field_goals_made' => 0,
                'three_point_field_goals_attempted' => 1,
                'free_throws_made' => 2,
                'free_throws_attempted' => 2,
                'offensive_rebounds' => 0,
                'defensive_rebounds' => 2,
                'steals' => 0,
                'blocks' => 0,
                'turnovers' => 1,
                'fouls' => 1,
                'plus_minus' => 0,
                'starter' => false,
                'ejected' => false,
                'did_not_play' => false,
                'active' => true,
            ]);
        }

        $findings = $this->service->audit(2026);

        $this->assertSame(1, $findings['mapping_gaps_espn']);
        $this->assertSame(1, $findings['mapping_gaps_tank01']);
        // Blocking metric is ESPN-only
        $this->assertSame(1, $findings['mapping_gaps']);
    }
}
