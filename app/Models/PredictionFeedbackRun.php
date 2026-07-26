<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionFeedbackRun extends Model
{
    protected $fillable = [
        'run_uuid',
        'status',
        'promoted',
        'champion_version',
        'challenger_version',
        'champion_report_id',
        'sample_size',
        'metrics',
        'challenger_params',
        'notes',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'promoted' => 'boolean',
        'sample_size' => 'integer',
        'metrics' => 'array',
        'challenger_params' => 'array',
        'notes' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function championReport(): BelongsTo
    {
        return $this->belongsTo(PredictionChampionReport::class, 'champion_report_id');
    }
}
