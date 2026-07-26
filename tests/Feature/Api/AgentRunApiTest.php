<?php

namespace Tests\Feature\Api;

use App\Models\WnbaAgentRun;
use App\Models\WnbaDataConflict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRunApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_triggering_an_analytics_agent_run(): void
    {
        // Sync queue in tests: the run executes inline.
        $response = $this->postJson('/api/wnba/data/agent-run', [
            'agent' => 'analytics',
            'dry_run' => true,
        ]);

        $response->assertStatus(202)->assertJson(['success' => true]);

        $run = WnbaAgentRun::first();
        $this->assertNotNull($run);
        $this->assertSame('analytics', $run->agent);
        $this->assertTrue((bool) $run->dry_run);
        $this->assertSame('success', $run->status);
    }

    public function test_triggering_an_entity_audit_run(): void
    {
        $this->postJson('/api/wnba/data/agent-run', ['agent' => 'entity'])
            ->assertStatus(202);

        $run = WnbaAgentRun::where('agent', 'entity')->first();
        $this->assertNotNull($run);
        $this->assertSame('audit', $run->mode);
        $this->assertContains($run->status, ['success', 'partial']);
    }

    public function test_invalid_agent_is_rejected(): void
    {
        $this->postJson('/api/wnba/data/agent-run', ['agent' => 'bogus'])
            ->assertStatus(422);
    }

    public function test_listing_and_showing_runs(): void
    {
        $this->postJson('/api/wnba/data/agent-run', ['agent' => 'analytics', 'dry_run' => true]);

        $list = $this->getJson('/api/wnba/data/agent-runs');
        $list->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $list->json('data'));

        $uuid = $list->json('data.0.run_uuid');
        $this->getJson("/api/wnba/data/agent-runs/{$uuid}")
            ->assertStatus(200)
            ->assertJsonPath('data.run_uuid', $uuid);

        $this->getJson('/api/wnba/data/agent-runs/not-a-run')->assertStatus(404);
    }

    public function test_review_queue_listing_and_resolution(): void
    {
        $conflict = WnbaDataConflict::create([
            'entity_type' => 'player',
            'entity_key' => '1,2',
            'field' => 'possible_duplicate',
            'candidates' => [],
            'resolution_reason' => 'players share normalized name',
            'requires_review' => true,
        ]);

        $queue = $this->getJson('/api/wnba/data/review-queue');
        $queue->assertStatus(200);
        $this->assertSame(1, $queue->json('data.count'));

        $this->postJson("/api/wnba/data/review-queue/{$conflict->id}/resolve", [
            'resolution_reason' => 'same person, merged manually',
        ])->assertStatus(200);

        $this->assertSame(0, $this->getJson('/api/wnba/data/review-queue')->json('data.count'));
        $this->assertNotNull($conflict->refresh()->resolved_at);
    }
}
