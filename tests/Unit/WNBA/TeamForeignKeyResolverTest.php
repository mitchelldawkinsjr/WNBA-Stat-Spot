<?php

namespace Tests\Unit\WNBA;

use App\Models\WnbaTeam;
use App\Services\WNBA\Data\Support\TeamForeignKeyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamForeignKeyResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_keys_are_external_ids_only(): void
    {
        $team = new WnbaTeam([
            'team_id' => '19',
            'espn_team_id' => '19',
            'tank01_team_id' => null,
        ]);
        $team->id = 10;

        $keys = TeamForeignKeyResolver::foreignKeysForTeam($team);

        $this->assertSame(['19'], $keys);
        $this->assertNotContains('10', $keys);
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
        $this->assertSame(['132052', '16'], $keys);
    }

    public function test_resolve_prefers_external_team_id_over_colliding_primary_key(): void
    {
        WnbaTeam::create([
            'team_id' => '17',
            'espn_team_id' => '17',
            'team_name' => 'Aces',
            'team_location' => 'Las Vegas',
            'team_abbreviation' => 'LV',
            'team_display_name' => 'Las Vegas Aces',
        ]);

        for ($i = 2; $i <= 17; $i++) {
            WnbaTeam::create([
                'team_id' => (string) (100000 + $i),
                'espn_team_id' => (string) (100000 + $i),
                'team_name' => "Team {$i}",
                'team_location' => 'City',
                'team_abbreviation' => 'T'.$i,
                'team_display_name' => "City Team {$i}",
            ]);
        }

        $this->assertSame(17, (int) WnbaTeam::where('team_id', '100017')->value('id'));
        $this->assertSame('T17', WnbaTeam::find(17)?->team_abbreviation);

        $resolved = TeamForeignKeyResolver::resolveTeam('17');

        $this->assertSame('LV', $resolved?->team_abbreviation);
        $this->assertSame('17', $resolved?->team_id);
    }
}
