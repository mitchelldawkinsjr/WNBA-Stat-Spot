<?php

namespace App\Services\WNBA\Agents;

use App\Models\WnbaRawPayload;

/**
 * Persists untouched provider payloads before normalization. Content hashing
 * makes storage idempotent: re-fetching an unchanged response is a no-op and
 * signals callers that downstream processing can be skipped.
 */
class RawPayloadStore
{
    /**
     * @param  array<int|string, mixed>  $payload
     * @return array{id: int|null, changed: bool}
     */
    public function store(
        string $sourceId,
        string $entityType,
        array $payload,
        ?int $season = null,
        ?string $endpoint = null,
    ): array {
        $hash = $this->hash($payload);

        $existing = WnbaRawPayload::query()
            ->where('source_id', $sourceId)
            ->where('entity_type', $entityType)
            ->where('content_hash', $hash)
            ->first();

        if ($existing !== null) {
            return ['id' => $existing->id, 'changed' => false];
        }

        $row = WnbaRawPayload::create([
            'source_id' => $sourceId,
            'entity_type' => $entityType,
            'endpoint' => $endpoint,
            'season' => $season,
            'content_hash' => $hash,
            'payload' => json_encode($payload),
            'record_count' => array_is_list($payload) ? count($payload) : null,
            'fetched_at' => now(),
        ]);

        return ['id' => $row->id, 'changed' => true];
    }

    public function prune(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? (int) config('wnba.agents.raw_payload_retention_days', 400);

        return WnbaRawPayload::query()
            ->where('fetched_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload));
    }
}
