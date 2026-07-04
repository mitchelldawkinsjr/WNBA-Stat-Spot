<?php

namespace Tests\Feature\Api;

use App\Models\GameScorePrediction;
use App\Models\TrackedPropPrediction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionAccuracyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_accuracy_endpoint_returns_dashboard_payload(): void
    {
        GameScorePrediction::query()->create([
            'game_id' => '401900100',
            'season' => 2026,
            'game_date' => now()->toDateString(),
            'home_team_abbr' => 'NYL',
            'away_team_abbr' => 'CON',
            'predicted_winner' => 'home',
            'predicted_home_score' => 84,
            'predicted_away_score' => 78,
            'predicted_total' => 162,
            'predicted_spread' => 6,
            'confidence' => 20,
            'predicted_at' => now()->subDay(),
            'actual_home_score' => 88,
            'actual_away_score' => 80,
            'actual_winner' => 'home',
            'winner_correct' => true,
            'home_score_error' => 4,
            'away_score_error' => 2,
            'total_error' => 6,
            'total_within_5' => false,
            'spread_direction_correct' => true,
            'graded_at' => now(),
        ]);

        TrackedPropPrediction::query()->create([
            'prediction_date' => now('America/New_York')->toDateString(),
            'game_id' => '401900100',
            'player_id' => '55',
            'player_name' => 'Star Player',
            'team_abbreviation' => 'NYL',
            'opponent' => 'vs CON',
            'stat_type' => 'points',
            'line' => 18.5,
            'predicted_value' => 21.2,
            'recommendation' => 'over',
            'confidence' => 70,
            'expected_value' => 5.5,
            'is_top_prop' => true,
            'rank' => 1,
            'predicted_at' => now(),
            'actual_value' => 22,
            'correct' => true,
            'graded_at' => now(),
        ]);

        $this->getJson('/api/wnba/predictions/accuracy?timezone=America/New_York')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.game_scores.winner_accuracy', 100)
            ->assertJsonPath('data.props.accuracy', 100)
            ->assertJsonPath('data.top_prop_of_day.player_name', 'Star Player');
    }

    public function test_top_prop_endpoint_returns_tracked_pick(): void
    {
        TrackedPropPrediction::query()->create([
            'prediction_date' => now('America/New_York')->toDateString(),
            'player_id' => '55',
            'player_name' => 'Star Player',
            'stat_type' => 'assists',
            'line' => 5.5,
            'predicted_value' => 6.8,
            'recommendation' => 'over',
            'confidence' => 68,
            'expected_value' => 3.1,
            'is_top_prop' => true,
            'rank' => 1,
            'predicted_at' => now(),
        ]);

        $this->getJson('/api/wnba/predictions/top-prop?timezone=America/New_York')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.player_name', 'Star Player')
            ->assertJsonPath('data.stat_type', 'assists');
    }
}
