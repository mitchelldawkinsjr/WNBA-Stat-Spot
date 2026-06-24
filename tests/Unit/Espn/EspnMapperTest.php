<?php

namespace Tests\Unit\Espn;

use App\Services\WNBA\Data\Mappers\EspnMapper;
use Tests\TestCase;

class EspnMapperTest extends TestCase
{
    public function test_map_summary_produces_player_and_team_records(): void
    {
        $summary = json_decode(
            file_get_contents(base_path('tests/Fixtures/espn_summary.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $mapper = new EspnMapper(2026);
        $mapped = $mapper->mapSummary($summary);

        $this->assertNotEmpty($mapped['player']);
        $this->assertNotEmpty($mapped['team']);

        $player = $mapped['player'][0];
        $this->assertSame('401857013', $player['game_id']);
        $this->assertNotEmpty($player['athlete_id']);
        $this->assertSame(21, $player['points']);
        $this->assertSame(7, $player['field_goals_made']);
        $this->assertSame(16, $player['field_goals_attempted']);

        $team = collect($mapped['team'])->firstWhere('team_abbreviation', 'ATL');
        $this->assertNotNull($team);
        $this->assertSame(94, $team['team_score']);
        $this->assertSame(34, $team['field_goals_made']);
    }

    public function test_map_player_gamelog_resolves_event_id_from_stats_object(): void
    {
        $payload = json_decode(<<<'JSON'
{
  "labels": ["MIN","PTS","REB"],
  "names": ["minutes","points","totalRebounds"],
  "events": {
    "401857014": {
      "gameDate": "2026-06-23T00:00:00.000+00:00",
      "atVs": "vs",
      "gameResult": "W",
      "score": "86-77",
      "opponent": {"id": "11", "displayName": "Phoenix Mercury", "abbreviation": "PHX"}
    }
  },
  "seasonTypes": [{
    "displayName": "2026 Regular Season",
    "categories": [{
      "events": [{
        "eventId": "401857014",
        "stats": ["29", "8", "9"]
      }]
    }]
  }]
}
JSON, true, 512, JSON_THROW_ON_ERROR);

        $mapper = new EspnMapper(2026);
        $rows = $mapper->mapPlayerGamelog('4432831', $payload);

        $this->assertCount(1, $rows);
        $this->assertSame('401857014', $rows[0]['game_id']);
        $this->assertSame('2026-06-23', $rows[0]['game_date']);
        $this->assertSame(29.0, $rows[0]['minutes']);
        $this->assertSame(8, $rows[0]['points']);
        $this->assertSame(9, $rows[0]['rebounds']);
        $this->assertSame('PHX', $rows[0]['opponent_team_abbreviation']);
    }

    public function test_map_schedule_dedupes_events(): void
    {
        $event = json_decode(<<<'JSON'
{
  "id": "401857013",
  "date": "2026-06-22T23:30Z",
  "season": {"year": 2026},
  "seasonType": {"name": "Regular Season"},
  "competitions": [{
    "competitors": [
      {"homeAway": "home", "team": {"id": "20", "name": "Dream", "location": "Atlanta", "abbreviation": "ATL", "displayName": "Atlanta Dream"}, "score": "94"},
      {"homeAway": "away", "team": {"id": "131935", "name": "Tempo", "location": "Toronto", "abbreviation": "TOR", "displayName": "Toronto Tempo"}, "score": "87"}
    ],
    "status": {"type": {"name": "STATUS_FINAL", "state": "post"}},
    "venue": {"fullName": "Gateway Center Arena"}
  }]
}
JSON, true, 512, JSON_THROW_ON_ERROR);

        $mapper = new EspnMapper(2026);
        $records = $mapper->mapSchedule([$event, $event]);

        $this->assertCount(1, $records);
        $this->assertSame('401857013', $records[0]['game_id']);
        $this->assertSame('20', $records[0]['home_team_id']);
    }
}
