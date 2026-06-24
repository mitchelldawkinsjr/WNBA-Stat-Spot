<?php

namespace Tests\Unit\WNBA;

use App\Services\WNBA\Data\Support\TeamCatalog;
use Tests\TestCase;

class TeamCatalogTest extends TestCase
{
    public function test_canonicalizes_provider_abbreviations(): void
    {
        $this->assertSame('LA', TeamCatalog::canonicalAbbreviation('LAS'));
        $this->assertSame('LV', TeamCatalog::canonicalAbbreviation('LVA'));
        $this->assertSame('NY', TeamCatalog::canonicalAbbreviation('NYL'));
        $this->assertSame('WSH', TeamCatalog::canonicalAbbreviation('WAS'));
        $this->assertSame('CON', TeamCatalog::canonicalAbbreviation('CONN'));
    }

    public function test_matches_tank01_team_by_alias_or_name(): void
    {
        $espnTeam = [
            'team_abbreviation' => 'LA',
            'team_location' => 'Los Angeles',
            'team_name' => 'Sparks',
        ];

        $tank01Teams = [
            [
                'team_id' => '1',
                'team_abbreviation' => 'LAS',
                'team_location' => 'Los Angeles',
                'team_name' => 'Sparks',
            ],
        ];

        $match = TeamCatalog::matchTank01Team($espnTeam, $tank01Teams);

        $this->assertNotNull($match);
        $this->assertSame('1', $match['team_id']);
    }

    public function test_excludes_exhibition_team_ids(): void
    {
        $this->assertFalse(TeamCatalog::isLeagueTeamId('17475'));
        $this->assertTrue(TeamCatalog::isLeagueTeamId('20'));
    }
}
