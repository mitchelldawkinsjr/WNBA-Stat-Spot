<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PredictionChampionReport;
use App\Models\PredictionFeedbackRun;
use App\Models\PredictionModelParam;
use App\Services\WNBA\Predictions\PredictionModelParamStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChampionReportController extends Controller
{
    public function __construct(
        private PredictionModelParamStore $paramStore
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $reports = PredictionChampionReport::query()
            ->orderByDesc('promoted_at')
            ->limit((int) $request->input('limit', 20))
            ->get()
            ->map(fn (PredictionChampionReport $report) => $this->listPayload($report));

        return response()->json([
            'success' => true,
            'data' => [
                'champion' => [
                    'version' => $this->paramStore->championVersion(),
                    'params' => $this->paramStore->champion(),
                ],
                'reports' => $reports,
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $report = PredictionChampionReport::query()
            ->where('report_uuid', $id)
            ->orWhere('id', ctype_digit($id) ? (int) $id : 0)
            ->first();

        if ($report === null) {
            return response()->json(['success' => false, 'message' => 'Report not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->detailPayload($report),
        ]);
    }

    public function feedbackRuns(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $runs = PredictionFeedbackRun::query()
            ->orderByDesc('started_at')
            ->limit((int) $request->input('limit', 20))
            ->get();

        return response()->json(['success' => true, 'data' => $runs]);
    }

    public function currentChampion(): JsonResponse
    {
        $row = PredictionModelParam::query()->champion()->orderByDesc('id')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'version' => $this->paramStore->championVersion(),
                'params' => $this->paramStore->champion(),
                'row' => $row,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(PredictionChampionReport $report): array
    {
        $brierBefore = $report->metrics_before['brier'] ?? null;
        $brierAfter = $report->metrics_after['brier'] ?? null;

        return [
            'report_uuid' => $report->report_uuid,
            'headline' => $report->headline,
            'from_version' => $report->from_version,
            'to_version' => $report->to_version,
            'promoted_at' => $report->promoted_at?->toIso8601String(),
            'brier_before' => $brierBefore,
            'brier_after' => $brierAfter,
            'brier_delta' => ($brierBefore !== null && $brierAfter !== null)
                ? round((float) $brierAfter - (float) $brierBefore, 6)
                : null,
            'reasons' => $report->reasons,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(PredictionChampionReport $report): array
    {
        return [
            'report_uuid' => $report->report_uuid,
            'feedback_run_id' => $report->feedback_run_id,
            'from_version' => $report->from_version,
            'to_version' => $report->to_version,
            'promoted_at' => $report->promoted_at?->toIso8601String(),
            'headline' => $report->headline,
            'summary_markdown' => $report->summary_markdown,
            'changes' => $report->changes,
            'metrics_before' => $report->metrics_before,
            'metrics_after' => $report->metrics_after,
            'reasons' => $report->reasons,
            'calibration_buckets' => $report->calibration_buckets,
        ];
    }
}
