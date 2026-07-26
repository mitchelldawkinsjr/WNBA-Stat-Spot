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
}
