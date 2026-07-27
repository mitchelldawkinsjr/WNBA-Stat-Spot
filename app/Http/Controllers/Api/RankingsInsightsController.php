<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\WnbaPlayer;
use App\Models\WnbaTeam;
use App\Services\WNBA\Analytics\AggregateStatsReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RankingsInsightsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AggregateStatsReader $aggregates,
    ) {}

    public function powerRankings(Request $request): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
            'as_of' => 'nullable|date',
        ]);

        $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
        $rankings = $this->aggregates->teamPowerRankings($season, $request->input('as_of'));

        $teams = WnbaTeam::query()
            ->whereIn('team_id', collect($rankings)->pluck('team_id')->all())
            ->get()
            ->keyBy('team_id');

        $data = array_map(function (array $row) use ($teams) {
            $team = $teams->get($row['team_id']);
            $row['team_abbreviation'] = $team?->team_abbreviation;
            $row['team_display_name'] = $team?->team_display_name;

            return $row;
        }, $rankings);

        return $this->successResponse([
            'season' => $season,
            'as_of_date' => $data[0]['as_of_date'] ?? null,
            'rankings' => $data,
        ], 'Power rankings retrieved');
    }

    public function dailyInsights(Request $request): JsonResponse
    {
        $request->validate([
            'season' => 'nullable|integer',
            'date' => 'nullable|date',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));
        $insights = $this->aggregates->dailyInsights(
            $season,
            $request->input('date'),
            (int) $request->input('limit', 15)
        );

        $playerIds = collect($insights)
            ->where('entity_type', 'player')
            ->pluck('entity_id')
            ->filter()
            ->unique()
            ->values();
        $players = $playerIds->isEmpty()
            ? collect()
            : WnbaPlayer::query()->whereIn('id', $playerIds)->get()->keyBy(fn ($p) => (string) $p->id);

        $teamIds = collect($insights)
            ->where('entity_type', 'team')
            ->pluck('entity_id')
            ->filter()
            ->unique()
            ->values();
        $teams = $teamIds->isEmpty()
            ? collect()
            : WnbaTeam::query()->whereIn('team_id', $teamIds)->get()->keyBy('team_id');

        $enriched = array_map(function (array $insight) use ($players, $teams) {
            if (($insight['entity_type'] ?? '') === 'player') {
                $player = $players->get((string) ($insight['entity_id'] ?? ''));
                $insight['athlete_id'] = $player?->athlete_id !== null ? (string) $player->athlete_id : null;
                $insight['href'] = $insight['athlete_id'] ? '/players/'.$insight['athlete_id'] : null;
            } elseif (($insight['entity_type'] ?? '') === 'team') {
                $team = $teams->get((string) ($insight['entity_id'] ?? ''));
                $insight['team_abbreviation'] = $team?->team_abbreviation;
                $insight['href'] = isset($insight['entity_id']) ? '/teams/'.$insight['entity_id'] : null;
            } else {
                $insight['href'] = null;
            }

            return $insight;
        }, $insights);

        return $this->successResponse([
            'season' => $season,
            'insight_date' => $enriched[0]['insight_date'] ?? $request->input('date'),
            'insights' => $enriched,
        ], 'Daily insights retrieved');
    }

    public function playerPercentiles(Request $request, string $playerId): JsonResponse
    {
        $request->validate(['season' => 'nullable|integer']);
        $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));

        $player = WnbaPlayer::query()
            ->where('athlete_id', $playerId)
            ->orWhere('id', $playerId)
            ->first();

        if ($player === null) {
            return $this->errorResponse('Player not found', null, 404);
        }

        $percentiles = $this->aggregates->playerPercentiles((int) $player->id, $season);
        if ($percentiles === null) {
            return $this->successResponse([
                'player_id' => (string) $player->athlete_id,
                'season' => $season,
                'percentiles' => null,
            ], 'Percentiles unavailable until analytics agent runs');
        }

        $percentiles['athlete_id'] = (string) $player->athlete_id;

        return $this->successResponse([
            'player_id' => (string) $player->athlete_id,
            'season' => $season,
            'percentiles' => $percentiles,
        ], 'Player percentiles retrieved');
    }

    public function teamPercentiles(Request $request, string $teamId): JsonResponse
    {
        $request->validate(['season' => 'nullable|integer']);
        $season = (int) ($request->input('season') ?? config('wnba.seasons.current_season'));

        $percentiles = $this->aggregates->teamPercentiles($teamId, $season);

        return $this->successResponse([
            'team_id' => $teamId,
            'season' => $season,
            'percentiles' => $percentiles,
        ], $percentiles !== null ? 'Team percentiles retrieved' : 'Percentiles unavailable until analytics agent runs');
    }
}
