<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PredictionModelParam extends Model
{
    protected $fillable = [
        'version',
        'status',
        'params',
        'metrics',
        'sample_size',
        'window_start',
        'window_end',
        'promoted_at',
    ];

    protected $casts = [
        'params' => 'array',
        'metrics' => 'array',
        'sample_size' => 'integer',
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'promoted_at' => 'datetime',
    ];

    public function scopeChampion(Builder $query): Builder
    {
        return $query->where('status', 'champion');
    }

    public function scopeChallenger(Builder $query): Builder
    {
        return $query->where('status', 'challenger');
    }
}
