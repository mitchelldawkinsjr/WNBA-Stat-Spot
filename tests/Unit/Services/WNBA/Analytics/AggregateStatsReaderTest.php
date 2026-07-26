<?php

namespace Tests\Unit\Services\WNBA\Analytics;

use App\Models\WnbaTeam;
use App\Models\WnbaTeamSeasonStat;
use App\Services\WNBA\Agents\AggregateComputationService;
use App\Services\WNBA\Analytics\AggregateStatsReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AggregateStatsReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_matchup_difficulty_from_league_median_drtg(): void
    {
        foreach ([['5', 90.0], ['9', 100.0], ['14', 110.0]] as [$teamId, $drtg]) {
            WnbaTeam::create([
                'team_id' => $teamId,
                'team_name' => "Team {$teamId}",
                'team_location' => 'City',
                'team_abbreviation' => 'T'.$teamId,
                'team_display_name' => "City {$teamId}",
            ]);

            WnbaTeamSeasonStat::create([
                'team_id' => $teamId,
                'season' => 2026,
                'games_played' => 10,
                'wins' => 5,
                'losses' => 5,
                'defensive_rating' => $drtg,
                'offensive_rating' => 100,
                'net_rating' => 100 - $drtg,
                'formula_version' => AggregateComputationService::FORMULA_VERSION,
                'computed_at' => now(),
            ]);
        }

        $reader = new AggregateStatsReader;

        $this->assertSame('difficult', $reader->matchupDifficulty('5', 2026));
        $this->assertSame('neutral', $reader->matchupDifficulty('9', 2026));
        $this->assertSame('favorable', $reader->matchupDifficulty('14', 2026));
        $this->assertNull($reader->matchupDifficulty(null, 2026));
        $this->assertSame(90.0, $reader->defensiveRating('5', 2026));
    }
}
