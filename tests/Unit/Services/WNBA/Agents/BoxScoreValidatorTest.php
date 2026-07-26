<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Services\WNBA\Agents\BoxScoreValidator;
use PHPUnit\Framework\TestCase;

class BoxScoreValidatorTest extends TestCase
{
    private BoxScoreValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BoxScoreValidator;
    }

    private function validRecord(array $overrides = []): array
    {
        return array_merge([
            'field_goals_made' => 8,
            'field_goals_attempted' => 14,
            'three_point_field_goals_made' => 2,
            'three_point_field_goals_attempted' => 5,
            'free_throws_made' => 4,
            'free_throws_attempted' => 4,
            'offensive_rebounds' => 2,
            'defensive_rebounds' => 3,
            'rebounds' => 5,
            'assists' => 4,
            'steals' => 1,
            'blocks' => 1,
            'turnovers' => 3,
            'fouls' => 2,
            'points' => 22, // 2*(8-2) + 3*2 + 4
            'minutes' => '30:00',
            'did_not_play' => false,
        ], $overrides);
    }

    public function test_valid_record_passes(): void
    {
        $result = $this->validator->validatePlayerRecord($this->validRecord());

        $this->assertSame(BoxScoreValidator::STATUS_VALID, $result['status']);
        $this->assertSame([], $result['failures']);
    }

    public function test_made_exceeding_attempted_is_invalid(): void
    {
        $result = $this->validator->validatePlayerRecord($this->validRecord([
            'field_goals_made' => 15, // > 14 attempts
            'points' => 36,
        ]));

        $this->assertSame(BoxScoreValidator::STATUS_INVALID, $result['status']);
    }

    public function test_points_mismatch_is_invalid(): void
    {
        $result = $this->validator->validatePlayerRecord($this->validRecord(['points' => 30]));

        $this->assertSame(BoxScoreValidator::STATUS_INVALID, $result['status']);
        $this->assertStringContainsString('points 30 != computed 22', implode('; ', $result['failures']));
    }

    public function test_negative_stat_is_invalid(): void
    {
        $result = $this->validator->validatePlayerRecord($this->validRecord(['steals' => -1]));

        $this->assertSame(BoxScoreValidator::STATUS_INVALID, $result['status']);
    }

    public function test_impossible_minutes_is_invalid(): void
    {
        $result = $this->validator->validatePlayerRecord($this->validRecord(['minutes' => '75:00']));

        $this->assertSame(BoxScoreValidator::STATUS_INVALID, $result['status']);
    }

    public function test_dnp_with_stats_is_warning(): void
    {
        $result = $this->validator->validatePlayerRecord($this->validRecord(['did_not_play' => true]));

        $this->assertSame(BoxScoreValidator::STATUS_WARNING, $result['status']);
    }

    public function test_rebound_component_mismatch_is_warning(): void
    {
        $result = $this->validator->validatePlayerRecord($this->validRecord(['rebounds' => 9]));

        $this->assertSame(BoxScoreValidator::STATUS_WARNING, $result['status']);
    }

    public function test_parse_minutes_formats(): void
    {
        $this->assertSame(30.0, $this->validator->parseMinutes('30:00'));
        $this->assertSame(24.5, $this->validator->parseMinutes('24:30'));
        $this->assertSame(18.0, $this->validator->parseMinutes('18'));
        $this->assertSame(18.4, $this->validator->parseMinutes(18.4));
        $this->assertNull($this->validator->parseMinutes(null));
        $this->assertNull($this->validator->parseMinutes(''));
        $this->assertNull($this->validator->parseMinutes('DNP'));
    }

    public function test_team_record_validation(): void
    {
        $valid = $this->validator->validateTeamRecord([
            'field_goals_made' => 30,
            'field_goals_attempted' => 70,
            'three_point_field_goals_made' => 8,
            'three_point_field_goals_attempted' => 25,
            'free_throws_made' => 12,
            'free_throws_attempted' => 15,
            'team_id' => '5',
            'opponent_team_id' => '9',
        ]);
        $this->assertSame(BoxScoreValidator::STATUS_VALID, $valid['status']);

        $invalid = $this->validator->validateTeamRecord([
            'field_goals_made' => 80,
            'field_goals_attempted' => 70,
            'team_id' => '5',
            'opponent_team_id' => '5',
        ]);
        $this->assertSame(BoxScoreValidator::STATUS_INVALID, $invalid['status']);
        $this->assertCount(2, $invalid['failures']);
    }
}
