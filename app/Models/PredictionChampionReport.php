<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionChampionReport extends Model
{
    protected $fillable = [
        'report_uuid',
        'feedback_run_id',
        'from_version',
        'to_version',
        'promoted_at',
        'headline',
        'summary_markdown',
        'changes',
        'metrics_before',
        'metrics_after',
        'reasons',
        'calibration_buckets',
    ];

    protected $casts = [
        'promoted_at' => 'datetime',
        'changes' => 'array',
        'metrics_before' => 'array',
        'metrics_after' => 'array',
        'reasons' => 'array',
        'calibration_buckets' => 'array',
    ];

    public function feedbackRun(): BelongsTo
    {
        return $this->belongsTo(PredictionFeedbackRun::class, 'feedback_run_id');
    }
}
