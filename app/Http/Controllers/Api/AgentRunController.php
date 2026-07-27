<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunAnalyticsAgent;
use App\Jobs\RunDataAgent;
use App\Jobs\RunEntityAgent;
use App\Models\WnbaAgentRun;
use App\Models\WnbaDataConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentRunController extends Controller
{
    /**
     * Trigger an agent run. The run executes on the queue worker; poll the
     * runs endpoint for the structured summary.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent' => 'required|in:data,analytics,entity',
            'mode' => 'nullable|in:incremental,backfill,repair,audit,live',
            'season' => 'nullable|integer',
            'dry_run' => 'nullable|boolean',
        ]);

        $params = [
            'mode' => $validated['mode'] ?? ($validated['agent'] === 'entity' ? 'audit' : 'incremental'),
            'season' => $validated['season'] ?? (int) config('wnba.seasons.current_season'),
            'dry_run' => (bool) ($validated['dry_run'] ?? false),
        ];

        match ($validated['agent']) {
            'data' => RunDataAgent::dispatch(array_merge($params, ['chain' => true])),
            'analytics' => RunAnalyticsAgent::dispatch($params),
            'entity' => RunEntityAgent::dispatch($params),
        };

        return response()->json([
            'success' => true,
            'message' => "Queued {$validated['agent']} agent run",
            'data' => $params,
        ], 202);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'agent' => 'nullable|in:data,analytics,entity',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $runs = WnbaAgentRun::query()
            ->when($request->input('agent'), fn ($query, $agent) => $query->where('agent', $agent))
            ->orderByDesc('started_at')
            ->limit((int) $request->input('limit', 20))
            ->get();

        return response()->json(['success' => true, 'data' => $runs]);
    }

    public function show(string $id): JsonResponse
    {
        $run = WnbaAgentRun::query()
            ->where('run_uuid', $id)
            ->orWhere('id', ctype_digit($id) ? (int) $id : 0)
            ->first();

        if ($run === null) {
            return response()->json(['success' => false, 'message' => 'Run not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $run]);
    }

    /**
     * Open review-queue items: conflicts and integrity findings that need a
     * human decision.
     */
    public function reviewQueue(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $items = WnbaDataConflict::query()
            ->where('requires_review', true)
            ->whereNull('resolved_at')
            ->when($request->input('entity_type'), fn ($query, $type) => $query->where('entity_type', $type))
            ->orderByDesc('created_at')
            ->limit((int) $request->input('limit', 50))
            ->get()
            ->map(fn (WnbaDataConflict $item) => $this->presentReviewItem($item));

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $items->count(),
                'items' => $items,
            ],
        ]);
    }

    /**
     * Resolve a review-queue item with an explicit decision.
     */
    public function resolveReviewItem(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution_reason' => 'required|string|max:500',
            'selected_value' => 'nullable|string|max:255',
        ]);

        $conflict = WnbaDataConflict::find($id);
        if ($conflict === null) {
            return response()->json(['success' => false, 'message' => 'Review item not found'], 404);
        }

        $conflict->update([
            'requires_review' => false,
            'resolved_at' => now(),
            'resolution_reason' => 'human review: '.$validated['resolution_reason'],
            'selected_value' => $validated['selected_value'] ?? $conflict->selected_value,
        ]);

        return response()->json(['success' => true, 'data' => $this->presentReviewItem($conflict->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentReviewItem(WnbaDataConflict $item): array
    {
        $payload = $item->toArray();
        $payload['entities'] = $this->resolveEntitiesForReview($item);

        return $payload;
    }

    /**
     * Attach lightweight entity snapshots so the UI can review without extra round-trips.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolveEntitiesForReview(WnbaDataConflict $item): array
    {
        if ($item->entity_type !== 'player') {
            return [];
        }

        $ids = collect(explode(',', (string) $item->entity_key))
            ->map(fn (string $id) => (int) trim($id))
            ->filter(fn (int $id) => $id > 0)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return \App\Models\WnbaPlayer::query()
            ->whereIn('id', $ids->all())
            ->get([
                'id',
                'athlete_id',
                'espn_athlete_id',
                'tank01_player_id',
                'athlete_display_name',
                'athlete_short_name',
            ])
            ->map(function ($player) {
                $games = \Illuminate\Support\Facades\DB::table('wnba_player_games')
                    ->where('player_id', $player->id)
                    ->count();

                return [
                    'id' => $player->id,
                    'athlete_id' => $player->athlete_id,
                    'espn_athlete_id' => $player->espn_athlete_id,
                    'tank01_player_id' => $player->tank01_player_id,
                    'name' => $player->athlete_display_name,
                    'short_name' => $player->athlete_short_name,
                    'games_count' => $games,
                    'profile_url' => $player->athlete_id
                        ? '/players/'.$player->athlete_id
                        : null,
                ];
            })
            ->all();
    }
}
