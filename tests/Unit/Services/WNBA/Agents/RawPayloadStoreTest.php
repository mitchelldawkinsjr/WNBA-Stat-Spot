<?php

namespace Tests\Unit\Services\WNBA\Agents;

use App\Models\WnbaRawPayload;
use App\Services\WNBA\Agents\RawPayloadStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawPayloadStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_payload_with_hash_and_metadata(): void
    {
        $store = new RawPayloadStore;
        $payload = [['game_id' => '401', 'points' => 20]];

        $result = $store->store('espn', 'player_box_scores', $payload, 2026);

        $this->assertTrue($result['changed']);
        $this->assertNotNull($result['id']);

        $row = WnbaRawPayload::find($result['id']);
        $this->assertSame('espn', $row->source_id);
        $this->assertSame('player_box_scores', $row->entity_type);
        $this->assertSame(2026, $row->season);
        $this->assertSame(1, $row->record_count);
        $this->assertSame($payload, json_decode($row->payload, true));
    }

    public function test_identical_payload_is_deduplicated(): void
    {
        $store = new RawPayloadStore;
        $payload = [['game_id' => '401', 'points' => 20]];

        $first = $store->store('espn', 'player_box_scores', $payload, 2026);
        $second = $store->store('espn', 'player_box_scores', $payload, 2026);

        $this->assertTrue($first['changed']);
        $this->assertFalse($second['changed']);
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, WnbaRawPayload::count());
    }

    public function test_changed_payload_creates_new_row(): void
    {
        $store = new RawPayloadStore;

        $store->store('espn', 'player_box_scores', [['points' => 20]], 2026);
        $result = $store->store('espn', 'player_box_scores', [['points' => 25]], 2026);

        $this->assertTrue($result['changed']);
        $this->assertSame(2, WnbaRawPayload::count());
    }

    public function test_oversized_payload_stores_hash_but_truncates_body(): void
    {
        config(['wnba.agents.raw_payload_max_bytes' => 100]);
        $store = new RawPayloadStore;
        $payload = [['play_text' => str_repeat('x', 500)]];

        $first = $store->store('sportsdataverse', 'play_by_play', $payload, 2026);
        $row = WnbaRawPayload::find($first['id']);

        $this->assertTrue($first['changed']);
        $this->assertSame(1, $row->record_count);
        $this->assertTrue(json_decode($row->payload, true)['truncated']);
        $this->assertLessThan(500, strlen($row->payload));

        // Dedupe still keys off the full-payload hash.
        $second = $store->store('sportsdataverse', 'play_by_play', $payload, 2026);
        $this->assertFalse($second['changed']);
        $this->assertSame($first['id'], $second['id']);
    }

    public function test_prune_removes_only_old_payloads(): void
    {
        $store = new RawPayloadStore;
        $store->store('espn', 'schedule', [['a' => 1]], 2026);
        WnbaRawPayload::query()->update(['fetched_at' => now()->subDays(500)]);
        $store->store('espn', 'schedule', [['b' => 2]], 2026);

        $deleted = $store->prune(400);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, WnbaRawPayload::count());
    }
}
