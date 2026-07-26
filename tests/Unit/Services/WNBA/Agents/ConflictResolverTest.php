<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Models\WnbaDataConflict;
use App\Services\WNBA\Agents\ConflictResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictResolverTest extends TestCase
{
    use RefreshDatabase;

    private ConflictResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ConflictResolver;
    }

    public function test_lower_priority_source_does_not_overwrite_higher(): void
    {
        $incomingWins = $this->resolver->resolveStatConflicts(
            'player_game_stat',
            '401|1234567',
            'espn',      // existing, priority 1
            'tank01',    // incoming, priority 2
            ['points' => 22],
            ['points' => 20],
        );

        $this->assertFalse($incomingWins);

        $conflict = WnbaDataConflict::first();
        $this->assertNotNull($conflict);
        $this->assertSame('espn', $conflict->selected_source);
        $this->assertSame('22', $conflict->selected_value);
        $this->assertCount(2, $conflict->candidates);
    }

    public function test_higher_priority_source_overwrites_lower(): void
    {
        $incomingWins = $this->resolver->resolveStatConflicts(
            'player_game_stat',
            '401|1234567',
            'sportsdataverse',
            'espn',
            ['points' => 20],
            ['points' => 22],
        );

        $this->assertTrue($incomingWins);
        $this->assertSame('espn', WnbaDataConflict::first()->selected_source);
    }

    public function test_same_source_update_is_not_a_conflict(): void
    {
        $incomingWins = $this->resolver->resolveStatConflicts(
            'player_game_stat',
            '401|1234567',
            'espn',
            'espn',
            ['points' => 12], // live partial
            ['points' => 22], // final
        );

        $this->assertTrue($incomingWins);
        $this->assertSame(0, WnbaDataConflict::count());
    }

    public function test_matching_values_produce_no_conflict_rows(): void
    {
        $this->resolver->resolveStatConflicts(
            'player_game_stat',
            '401|1234567',
            'espn',
            'tank01',
            ['points' => 22, 'rebounds' => 5],
            ['points' => 22, 'rebounds' => 5],
        );

        $this->assertSame(0, WnbaDataConflict::count());
    }

    public function test_large_disagreement_requires_review(): void
    {
        $this->resolver->resolveStatConflicts(
            'player_game_stat',
            '401|1234567',
            'espn',
            'tank01',
            ['points' => 30],
            ['points' => 20],
        );

        $this->assertTrue((bool) WnbaDataConflict::first()->requires_review);
    }

    public function test_small_disagreement_is_auto_resolved(): void
    {
        $this->resolver->resolveStatConflicts(
            'player_game_stat',
            '401|1234567',
            'espn',
            'tank01',
            ['points' => 22],
            ['points' => 21],
        );

        $conflict = WnbaDataConflict::first();
        $this->assertFalse((bool) $conflict->requires_review);
        $this->assertNotNull($conflict->resolved_at);
    }
}
