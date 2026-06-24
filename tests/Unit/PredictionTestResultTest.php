<?php

namespace Tests\Unit;

use App\Models\PredictionTestResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionTestResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_player_rankings_uses_postgres_compatible_having_clause(): void
    {
        PredictionTestResult::create([
            'test_batch_id' => 'batch_1',
            'test_type' => 'historical',
            'player_id' => '3142191',
            'player_name' => 'Test Player',
            'player_position' => 'G',
            'stat_type' => 'points',
            'test_games' => 3,
            'betting_lines' => [10.5],
            'season_average' => 12.5,
            'total_predictions' => 10,
            'correct_predictions' => 8,
            'accuracy_percentage' => 80.0,
            'line_results' => [],
            'actual_game_results' => [],
            'sample_size' => 10,
            'tested_at' => now(),
        ]);

        $rankings = PredictionTestResult::getPlayerRankings();

        $this->assertCount(1, $rankings);
        $this->assertSame('3142191', $rankings->first()->player_id);
        $this->assertEquals(80.0, (float) $rankings->first()->avg_accuracy);
    }
}
