<?php

namespace App\Services\WNBA\Predictions;

use App\Models\GameScorePrediction;
use App\Models\PredictionChampionReport;
use App\Models\PredictionFeedbackRun;
use App\Models\TrackedPropPrediction;
use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PredictionAccuracyService
{
    public function __construct(
        private PredictionModelParamStore $paramStore
    ) {}

    /**
     * Persist a game score prediction. Does not overwrite graded rows.
     *
     * @param  array<string, mixed>  $preview
     */
    public function recordGameScorePrediction(array $preview): ?GameScorePrediction
    {
        $prediction = $preview['prediction'] ?? null;
        $game = $preview['game'] ?? null;

        if (! is_array($prediction) || ! is_array($game)) {
            return null;
        }

        $gameId = (string) ($game['game_id'] ?? '');
        if ($gameId === '') {
            return null;
        }

        $existing = GameScorePrediction::query()->where('game_id', $gameId)->first();
        if ($existing !== null && $existing->graded_at !== null) {
            return $existing;
        }

        $projected = $prediction['projected_score'] ?? [];
        $winProb = $prediction['win_probability'] ?? [];

        $payload = [
            'season' => (int) ($game['season'] ?? config('wnba.seasons.current_season')),
            'game_date' => $this->parseDate($game['game_date'] ?? $game['game_date_time'] ?? null),
            'home_team_abbr' => $preview['home_team']['abbreviation'] ?? null,
            'away_team_abbr' => $preview['away_team']['abbreviation'] ?? null,
            'predicted_winner' => (string) ($prediction['predicted_winner'] ?? 'home'),
            'predicted_home_score' => (float) ($projected['home'] ?? 0),
            'predicted_away_score' => (float) ($projected['away'] ?? 0),
            'predicted_total' => (float) ($projected['total'] ?? 0),
            'predicted_spread' => (float) ($prediction['projected_spread'] ?? 0),
            'win_probability_home' => isset($winProb['home']) ? (float) $winProb['home'] : null,
            'win_probability_away' => isset($winProb['away']) ? (float) $winProb['away'] : null,
            'confidence' => isset($prediction['confidence']) ? (float) $prediction['confidence'] : null,
            'feature_snapshot' => $preview['feature_snapshot']
                ?? $prediction['feature_snapshot']
                ?? $preview['factors']
                ?? null,
            'model_version' => $preview['model_version']
                ?? $prediction['model_version']
                ?? $this->paramStore->championVersion(),
            'predicted_at' => now(),
        ];

        if ($existing !== null) {
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return GameScorePrediction::query()->create(array_merge($payload, [
            'game_id' => $gameId,
        ]));
    }

    /**
     * Persist today's prop recommendations. Marks rank 1 as the top prop of the day.
     *
     * @param  list<array<string, mixed>>  $props
     * @return list<TrackedPropPrediction>
     */
    public function recordTodaysProps(array $props, ?string $timezone = 'America/New_York'): array
    {
        if ($props === []) {
            return [];
        }

        $predictionDate = Carbon::now($timezone ?? 'America/New_York')->toDateString();
        $recorded = [];
        $championVersion = $this->paramStore->championVersion();

        TrackedPropPrediction::query()
            ->whereDate('prediction_date', $predictionDate)
            ->update(['is_top_prop' => false]);

        foreach (array_values($props) as $index => $prop) {
            $playerId = (string) ($prop['player_id'] ?? '');
            $statType = (string) ($prop['stat_type'] ?? '');
            $line = (float) ($prop['suggested_line'] ?? $prop['line'] ?? 0);
            $recommendation = (string) ($prop['recommendation'] ?? 'avoid');

            if ($playerId === '' || $statType === '' || $recommendation === 'avoid') {
                continue;
            }

            $rank = $index + 1;
            $row = TrackedPropPrediction::query()->updateOrCreate(
                [
                    'prediction_date' => $predictionDate,
                    'player_id' => $playerId,
                    'stat_type' => $statType,
                    'line' => $line,
                ],
                [
                    'game_id' => isset($prop['game_id']) ? (string) $prop['game_id'] : null,
                    'player_name' => (string) ($prop['player_name'] ?? 'Unknown'),
                    'team_abbreviation' => $prop['team_abbreviation'] ?? null,
                    'opponent' => $prop['opponent'] ?? null,
                    'predicted_value' => (float) ($prop['predicted_value'] ?? $line),
                    'recommendation' => $recommendation,
                    'confidence' => isset($prop['confidence']) ? (float) $prop['confidence'] : null,
                    'expected_value' => isset($prop['expected_value']) ? (float) $prop['expected_value'] : null,
                    'probability_over' => isset($prop['probability_over']) ? (float) $prop['probability_over'] : null,
                    'probability_under' => isset($prop['probability_under']) ? (float) $prop['probability_under'] : null,
                    'betting_value' => $prop['betting_value'] ?? null,
                    'reasoning' => $prop['reasoning'] ?? null,
                    'feature_snapshot' => $prop['feature_snapshot'] ?? null,
                    'model_version' => $prop['model_version'] ?? $championVersion,
                    'is_top_prop' => $rank === 1,
                    'rank' => $rank,
                    'predicted_at' => now(),
                ]
            );

            $recorded[] = $row;
        }

        return $recorded;
    }

    public function gradePendingPredictions(): array
    {
        return [
            'game_scores_graded' => $this->gradePendingGameScores(),
            'props_graded' => $this->gradePendingProps(),
        ];
    }

    public function gradePendingGameScores(): int
    {
        $graded = 0;

        GameScorePrediction::query()
            ->ungraded()
            ->orderBy('id')
            ->each(function (GameScorePrediction $prediction) use (&$graded) {
                if ($this->gradeGameScorePrediction($prediction)) {
                    $graded++;
                }
            });

        return $graded;
    }

    public function gradePendingProps(): int
    {
        $graded = 0;

        TrackedPropPrediction::query()
            ->ungraded()
            ->where('recommendation', '!=', 'avoid')
            ->orderBy('id')
            ->each(function (TrackedPropPrediction $prediction) use (&$graded) {
                if ($this->gradePropPrediction($prediction)) {
                    $graded++;
                }
            });

        return $graded;
    }

    public function gradeGameScorePrediction(GameScorePrediction $prediction): bool
    {
        $game = WnbaGame::query()
            ->with('gameTeams')
            ->where('game_id', $prediction->game_id)
            ->first();

        if ($game === null || ! $this->gameIsFinal($game)) {
            return false;
        }

        $homeLine = $game->gameTeams->firstWhere('home_away', 'home');
        $awayLine = $game->gameTeams->firstWhere('home_away', 'away');

        if ($homeLine === null || $awayLine === null) {
            return false;
        }

        $actualHome = (int) $homeLine->team_score;
        $actualAway = (int) $awayLine->team_score;

        if ($actualHome === 0 && $actualAway === 0) {
            return false;
        }

        $actualWinner = $actualHome > $actualAway ? 'home' : 'away';
        $actualTotal = $actualHome + $actualAway;
        $actualMargin = $actualHome - $actualAway;

        $homeError = abs($prediction->predicted_home_score - $actualHome);
        $awayError = abs($prediction->predicted_away_score - $actualAway);
        $totalError = abs($prediction->predicted_total - $actualTotal);

        $prediction->fill([
            'actual_home_score' => $actualHome,
            'actual_away_score' => $actualAway,
            'actual_winner' => $actualWinner,
            'winner_correct' => $prediction->predicted_winner === $actualWinner,
            'home_score_error' => round($homeError, 1),
            'away_score_error' => round($awayError, 1),
            'total_error' => round($totalError, 1),
            'total_within_5' => $totalError <= 5,
            'spread_direction_correct' => $this->spreadDirectionCorrect(
                (float) $prediction->predicted_spread,
                $actualMargin
            ),
            'graded_at' => now(),
        ]);
        $prediction->save();

        return true;
    }

    public function gradePropPrediction(TrackedPropPrediction $prediction): bool
    {
        $player = WnbaPlayer::findByExternalId((string) $prediction->player_id);
        if ($player === null && ctype_digit((string) $prediction->player_id)) {
            $player = WnbaPlayer::query()->find((int) $prediction->player_id);
        }

        if ($player === null) {
            return false;
        }

        $gameQuery = WnbaPlayerGame::query()->where('player_id', $player->id);

        if ($prediction->game_id) {
            $game = WnbaGame::query()->where('game_id', $prediction->game_id)->first();
            if ($game === null || ! $this->gameIsFinal($game)) {
                return false;
            }
            $gameQuery->where('game_id', $game->id);
        } else {
            $gameQuery->whereHas('game', function ($query) use ($prediction) {
                $query->whereDate('game_date', $prediction->prediction_date)
                    ->where(function ($statusQuery) {
                        $statusQuery->where('status_name', 'STATUS_FINAL')
                            ->orWhere('status_type', 'final')
                            ->orWhere('status_abbreviation', 'F');
                    });
            });
        }

        $playerGame = $gameQuery->orderByDesc('id')->first();
        if ($playerGame === null || $playerGame->did_not_play) {
            return false;
        }

        $statColumn = $this->statColumn($prediction->stat_type);
        if ($statColumn === null || ! isset($playerGame->{$statColumn})) {
            return false;
        }

        $actual = (float) $playerGame->{$statColumn};
        $line = (float) $prediction->line;
        $recommendation = $prediction->recommendation;

        // Push: record actual but exclude from accuracy (correct stays null).
        $correct = null;
        if (abs($actual - $line) >= 0.0001) {
            if ($recommendation === 'over') {
                $correct = $actual > $line;
            } elseif ($recommendation === 'under') {
                $correct = $actual < $line;
            } else {
                return false;
            }
        }

        $prediction->fill([
            'actual_value' => $actual,
            'correct' => $correct,
            'graded_at' => now(),
        ]);
        $prediction->save();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccuracyDashboard(?string $timezone = 'America/New_York'): array
    {
        $this->gradePendingPredictions();

        $gameScores = GameScorePrediction::query()->graded()->get();
        $props = TrackedPropPrediction::query()->graded()->whereNotNull('correct')->get();
        $topProps = TrackedPropPrediction::query()->graded()->topProps()->whereNotNull('correct')->get();

        $winnerCorrect = $gameScores->where('winner_correct', true)->count();
        $totalWithin5 = $gameScores->where('total_within_5', true)->count();
        $spreadCorrect = $gameScores->where('spread_direction_correct', true)->count();
        $gameCount = $gameScores->count();

        $propCorrect = $props->where('correct', true)->count();
        $propCount = $props->count();
        $topPropCorrect = $topProps->where('correct', true)->count();
        $topPropCount = $topProps->count();

        $avgHomeError = $gameCount > 0 ? round($gameScores->avg('home_score_error'), 1) : null;
        $avgAwayError = $gameCount > 0 ? round($gameScores->avg('away_score_error'), 1) : null;
        $avgTotalError = $gameCount > 0 ? round($gameScores->avg('total_error'), 1) : null;

        $pendingGames = GameScorePrediction::query()->ungraded()->count();
        $pendingProps = TrackedPropPrediction::query()->ungraded()->count();

        return [
            'game_scores' => [
                'graded' => $gameCount,
                'pending' => $pendingGames,
                'winner_accuracy' => $this->pct($winnerCorrect, $gameCount),
                'winner_correct' => $winnerCorrect,
                'total_within_5_pct' => $this->pct($totalWithin5, $gameCount),
                'spread_direction_accuracy' => $this->pct($spreadCorrect, $gameCount),
                'avg_home_score_error' => $avgHomeError,
                'avg_away_score_error' => $avgAwayError,
                'avg_total_error' => $avgTotalError,
                'recent' => $gameScores->sortByDesc('graded_at')->take(10)->values()->map(fn (GameScorePrediction $row) => [
                    'game_id' => $row->game_id,
                    'matchup' => trim(($row->away_team_abbr ?? 'AWAY').' @ '.($row->home_team_abbr ?? 'HOME')),
                    'predicted' => $row->away_team_abbr.' '.$row->predicted_away_score.' – '.$row->home_team_abbr.' '.$row->predicted_home_score,
                    'actual' => $row->away_team_abbr.' '.$row->actual_away_score.' – '.$row->home_team_abbr.' '.$row->actual_home_score,
                    'winner_correct' => $row->winner_correct,
                    'total_error' => $row->total_error,
                    'graded_at' => $row->graded_at?->toIso8601String(),
                ])->all(),
            ],
            'props' => [
                'graded' => $propCount,
                'pending' => $pendingProps,
                'accuracy' => $this->pct($propCorrect, $propCount),
                'correct' => $propCorrect,
                'by_stat' => $props->groupBy('stat_type')->map(function ($group, $stat) {
                    $total = $group->count();
                    $correct = $group->where('correct', true)->count();

                    return [
                        'stat_type' => $stat,
                        'graded' => $total,
                        'accuracy' => $this->pct($correct, $total),
                    ];
                })->values()->all(),
                'recent' => $props->sortByDesc('graded_at')->take(10)->values()->map(fn (TrackedPropPrediction $row) => [
                    'player_id' => $row->player_id,
                    'player_name' => $row->player_name,
                    'team_abbreviation' => $row->team_abbreviation,
                    'opponent' => $row->opponent,
                    'game_id' => $row->game_id,
                    'stat_type' => $row->stat_type,
                    'recommendation' => $row->recommendation,
                    'line' => $row->line,
                    'predicted_value' => $row->predicted_value,
                    'actual_value' => $row->actual_value,
                    'correct' => $row->correct,
                    'is_top_prop' => (bool) $row->is_top_prop,
                    'prediction_date' => $row->prediction_date?->toDateString(),
                    'graded_at' => $row->graded_at?->toIso8601String(),
                ])->all(),
                'top_prop_accuracy' => $this->pct($topPropCorrect, $topPropCount),
                'top_prop_graded' => $topPropCount,
                'top_prop_correct' => $topPropCorrect,
            ],
            'top_prop_of_day' => $this->getTopPropOfDay($timezone),
            'model' => $this->modelFeedbackSummary(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modelFeedbackSummary(): array
    {
        $champion = $this->paramStore->champion();
        $latestRun = PredictionFeedbackRun::query()->orderByDesc('id')->first();
        $latestReport = PredictionChampionReport::query()->orderByDesc('promoted_at')->first();

        return [
            'model_version' => $champion['version'],
            'gates' => $champion['gates'],
            'calibration' => $champion['calibration'],
            'adjustments' => $champion['adjustments'],
            'auto_tune_enabled' => (bool) config('wnba.predictions.auto_tune_enabled', true),
            'latest_feedback_run' => $latestRun === null ? null : [
                'run_uuid' => $latestRun->run_uuid,
                'status' => $latestRun->status,
                'promoted' => $latestRun->promoted,
                'sample_size' => $latestRun->sample_size,
                'metrics' => $latestRun->metrics,
                'champion_version' => $latestRun->champion_version,
                'challenger_version' => $latestRun->challenger_version,
                'champion_report_id' => $latestRun->champion_report_id,
                'finished_at' => $latestRun->finished_at?->toIso8601String(),
            ],
            'latest_champion_report' => $latestReport === null ? null : [
                'report_uuid' => $latestReport->report_uuid,
                'headline' => $latestReport->headline,
                'from_version' => $latestReport->from_version,
                'to_version' => $latestReport->to_version,
                'promoted_at' => $latestReport->promoted_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTopPropOfDay(?string $timezone = 'America/New_York'): ?array
    {
        $predictionDate = Carbon::now($timezone ?? 'America/New_York')->toDateString();

        $prop = TrackedPropPrediction::query()
            ->whereDate('prediction_date', $predictionDate)
            ->where('is_top_prop', true)
            ->orderBy('rank')
            ->first();

        if ($prop === null) {
            $prop = TrackedPropPrediction::query()
                ->whereDate('prediction_date', $predictionDate)
                ->orderBy('rank')
                ->orderByDesc('expected_value')
                ->first();
        }

        if ($prop === null) {
            return null;
        }

        return [
            'player_id' => $prop->player_id,
            'player_name' => $prop->player_name,
            'team_abbreviation' => $prop->team_abbreviation,
            'opponent' => $prop->opponent,
            'game_id' => $prop->game_id,
            'stat_type' => $prop->stat_type,
            'suggested_line' => $prop->line,
            'predicted_value' => $prop->predicted_value,
            'recommendation' => $prop->recommendation,
            'confidence' => $prop->confidence,
            'expected_value' => $prop->expected_value,
            'probability_over' => $prop->probability_over,
            'probability_under' => $prop->probability_under,
            'betting_value' => $prop->betting_value,
            'reasoning' => $prop->reasoning,
            'is_top_prop' => true,
            'actual_value' => $prop->actual_value,
            'correct' => $prop->correct,
            'graded' => $prop->graded_at !== null,
        ];
    }

    private function gameIsFinal(WnbaGame $game): bool
    {
        $statusName = strtoupper((string) $game->status_name);
        $statusType = strtolower((string) $game->status_type);
        $abbr = strtoupper((string) $game->status_abbreviation);

        return str_contains($statusName, 'FINAL')
            || $statusType === 'final'
            || $abbr === 'F'
            || $abbr === 'FINAL';
    }

    private function spreadDirectionCorrect(float $predictedSpread, int $actualMargin): bool
    {
        if (abs($predictedSpread) < 0.5) {
            return abs($actualMargin) <= 3;
        }

        return ($predictedSpread > 0 && $actualMargin > 0)
            || ($predictedSpread < 0 && $actualMargin < 0);
    }

    private function statColumn(string $statType): ?string
    {
        return match ($statType) {
            'points' => 'points',
            'rebounds' => 'rebounds',
            'assists' => 'assists',
            'steals' => 'steals',
            'blocks' => 'blocks',
            'turnovers' => 'turnovers',
            default => null,
        };
    }

    private function pct(int $correct, int $total): ?float
    {
        if ($total <= 0) {
            return null;
        }

        return round(($correct / $total) * 100, 1);
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $e) {
            Log::debug('Unable to parse prediction game date', ['value' => $value]);

            return null;
        }
    }
}
