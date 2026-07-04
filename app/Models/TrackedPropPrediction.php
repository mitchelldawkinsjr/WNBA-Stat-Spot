<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrackedPropPrediction extends Model
{
    protected $fillable = [
        'prediction_date',
        'game_id',
        'player_id',
        'player_name',
        'team_abbreviation',
        'opponent',
        'stat_type',
        'line',
        'predicted_value',
        'recommendation',
        'confidence',
        'expected_value',
        'probability_over',
        'probability_under',
        'betting_value',
        'reasoning',
        'is_top_prop',
        'rank',
        'actual_value',
        'correct',
        'predicted_at',
        'graded_at',
    ];

    protected $casts = [
        'prediction_date' => 'date',
        'line' => 'float',
        'predicted_value' => 'float',
        'confidence' => 'float',
        'expected_value' => 'float',
        'probability_over' => 'float',
        'probability_under' => 'float',
        'is_top_prop' => 'boolean',
        'rank' => 'integer',
        'actual_value' => 'float',
        'correct' => 'boolean',
        'predicted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    public function scopeUngraded(Builder $query): Builder
    {
        return $query->whereNull('graded_at');
    }

    public function scopeGraded(Builder $query): Builder
    {
        return $query->whereNotNull('graded_at');
    }

    public function scopeTopProps(Builder $query): Builder
    {
        return $query->where('is_top_prop', true);
    }
}
