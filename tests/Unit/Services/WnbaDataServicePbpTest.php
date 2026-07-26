<?php

namespace Tests\Unit\Services;

use App\Models\WnbaGame;
use App\Models\WnbaPlay;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\Providers\SportsDataverseWnbaProvider;
use App\Services\WnbaDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WnbaDataServicePbpTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_pbp_data_handles_normalized_and_teamless_records(): void
    {
        $service = new WnbaDataService(app(SportsDataverseWnbaProvider::class));

        $service->savePbpData([
            [
                'game_id' => '401736100',
                'season' => 2026,
                'season_type' => 2,
                'game_date' => '2026-07-20',
                'game_date_time' => '2026-07-20 19:00:00',
                'play_id' => '4017361001',
                'play_sequence_number' => 1,
                'period' => '1',
                'period_display_value' => '1st Quarter',
                'clock_display_value' => '9:45',
                'team_id' => '17',
                'team_name' => 'Aces',
                'team_location' => 'Las Vegas',
                'team_abbreviation' => 'LV',
                'team_display_name' => 'Las Vegas Aces',
                'play_type_id' => '110',
                'play_type_text' => 'Layup',
                'play_type_abbreviation' => '',
                'play_text' => 'A. Wilson makes layup',
                'athlete_id' => null,
                'score_value' => 2,
                'score_team_id' => '17',
            ],
            [
                // End-of-period rows have no team attribution.
                'game_id' => '401736100',
                'season' => 2026,
                'season_type' => 2,
                'game_date' => '2026-07-20',
                'game_date_time' => '2026-07-20 19:00:00',
                'play_id' => '4017361002',
                'play_sequence_number' => 2,
                'period' => '1',
                'period_display_value' => '1st Quarter',
                'clock_display_value' => '0:00',
                'team_id' => '',
                'team_name' => null,
                'team_location' => null,
                'team_abbreviation' => null,
                'team_display_name' => null,
                'play_type_id' => '412',
                'play_type_text' => 'End Period',
                'play_type_abbreviation' => '',
                'play_text' => 'End of 1st Quarter',
                'athlete_id' => null,
                'score_value' => 0,
                'score_team_id' => null,
            ],
        ]);

        $this->assertSame(2, WnbaPlay::count());
        $this->assertSame(1, WnbaGame::count());
        $this->assertSame(1, WnbaTeam::count());
        $this->assertSame('17', WnbaPlay::where('play_id', '4017361001')->first()->team_id);
        $this->assertNull(WnbaPlay::where('play_id', '4017361002')->first()->team_id);
    }

    public function test_save_pbp_data_does_not_overwrite_existing_team_metadata(): void
    {
        WnbaTeam::create([
            'team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
            'team_logo' => 'https://example.com/lv.png',
        ]);

        $service = new WnbaDataService(app(SportsDataverseWnbaProvider::class));
        $service->savePbpData([
            [
                'game_id' => '401736100',
                'season' => 2026,
                'season_type' => 2,
                'game_date' => '2026-07-20',
                'game_date_time' => '2026-07-20 19:00:00',
                'play_id' => '4017361001',
                'play_sequence_number' => 1,
                'period' => '1',
                'period_display_value' => '1st Quarter',
                'clock_display_value' => '9:45',
                'team_id' => '17',
                'team_name' => null,
                'team_location' => null,
                'team_abbreviation' => null,
                'team_display_name' => null,
                'play_type_id' => '110',
                'play_type_text' => 'Layup',
                'play_type_abbreviation' => '',
                'play_text' => 'A. Wilson makes layup',
                'athlete_id' => null,
                'score_value' => 2,
                'score_team_id' => '17',
            ],
        ]);

        $team = WnbaTeam::where('team_id', '17')->first();
        $this->assertSame('Aces', $team->team_name);
        $this->assertSame('https://example.com/lv.png', $team->team_logo);
    }
}
