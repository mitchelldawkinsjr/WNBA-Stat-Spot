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

    public function test_identical_home_and_away_team_is_flagged(): void
    {
        WnbaTeam::create([
            'team_id' => '131935',
            'team_name' => 'Tempo',
            'team_location' => 'Toronto',
            'team_abbreviation' => 'TOR',
            'team_display_name' => 'Toronto Tempo',
        ]);

        $game = \App\Models\WnbaGame::create([
            'game_id' => '401857083',
            'season' => 2026,
            'season_type' => 2,
            'game_date' => '2026-07-21',
            'game_date_time' => '2026-07-21 00:00:00',
        ]);

        \App\Models\WnbaGameTeam::create([
            'game_id' => $game->id,
            'team_id' => '131935',
            'opponent_team_id' => '131935',
            'home_away' => 'home',
            'team_winner' => false,
            'team_score' => 83,
            'opponent_team_score' => 109,
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
        ]);

        $findings = $this->service->audit(2026);

        $this->assertGreaterThan(0, $findings['identical_opponents']);
        $this->assertTrue(
            WnbaDataConflict::where('field', 'identical_opponents')->exists()
        );
    }
}
