<?php

namespace Tests\Unit\WNBA;

use App\Models\WnbaTeam;
use App\Services\WNBA\Data\Support\TeamForeignKeyResolver;
use Tests\TestCase;

class TeamForeignKeyResolverTest extends TestCase
{
    public function test_foreign_keys_include_canonical_and_legacy_ids(): void
    {
        $team = new WnbaTeam([
            'team_id' => '19',
            'espn_team_id' => '19',
            'tank01_team_id' => null,
        ]);
        $team->id = 10;

        $keys = TeamForeignKeyResolver::foreignKeysForTeam($team);

        $this->assertSame(['19', '10'], $keys);
    }

    public function test_foreign_keys_include_provider_ids_for_expansion_teams(): void
    {
        $team = new WnbaTeam([
            'id' => 16,
            'team_id' => '132052',
            'espn_team_id' => '132052',
            'tank01_team_id' => '16',
        ]);

        $keys = TeamForeignKeyResolver::foreignKeysForTeam($team);

        $this->assertContains('132052', $keys);
        $this->assertContains('16', $keys);
    }
}
