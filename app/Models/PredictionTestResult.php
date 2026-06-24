<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PredictionTestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_batch_id',
        'test_type',
        'player_id',
        'player_name',
        'player_position',
        'stat_type',
        'test_games',
        'betting_lines',
        'season_average',
        'total_predictions',
        'correct_predictions',
        'accuracy_percentage',
        'confidence_score',
        'line_results',
        'actual_game_results',
        'insights',
        'best_line_accuracy',
        'worst_line_accuracy',
        'average_line_accuracy',
        'volatility_score',
        'sample_size',
        'data_quality_score',
        'tested_at',
        'test_version'
    ];

    protected $casts = [
        'betting_lines' => 'array',
        'line_results' => 'array',
        'actual_game_results' => 'array',
        'insights' => 'array',
        'season_average' => 'decimal:2',
        'accuracy_percentage' => 'decimal:2',
        'confidence_score' => 'decimal:3',
        'best_line_accuracy' => 'decimal:2',
        'worst_line_accuracy' => 'decimal:2',
        'average_line_accuracy' => 'decimal:2',
        'volatility_score' => 'decimal:3',
        'data_quality_score' => 'decimal:3',
        'tested_at' => 'datetime'
    ];

    // Relationships
    public function player()
    {
        return $this->belongsTo(WnbaPlayer::class, 'player_id', 'athlete_id');
    }

    // Scopes for efficient querying
    public function scopeByPlayer(Builder $query, string $playerId): Builder
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeByStat(Builder $query, string $statType): Builder
    {
        return $query->where('stat_type', $statType);
    }

    public function scopeHighAccuracy(Builder $query, float $threshold = 80.0): Builder
    {
        return $query->where('accuracy_percentage', '>=', $threshold);
    }

    public function scopeRecentTests(Builder $query, int $days = 30): Builder
    {
        return $query->where('tested_at', '>=', now()->subDays($days));
    }

    public function scopeByTestType(Builder $query, string $testType): Builder
    {
        return $query->where('test_type', $testType);
    }

    public function scopeOrderByAccuracy(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('accuracy_percentage', $direction);
    }

    // Helper methods
    public function getAccuracyGrade(): string
    {
        if ($this->accuracy_percentage >= 90) return 'A+';
        if ($this->accuracy_percentage >= 85) return 'A';
        if ($this->accuracy_percentage >= 80) return 'B+';
        if ($this->accuracy_percentage >= 75) return 'B';
        if ($this->accuracy_percentage >= 70) return 'C+';
        if ($this->accuracy_percentage >= 65) return 'C';
        if ($this->accuracy_percentage >= 60) return 'D';
        return 'F';
    }

    public function getPerformanceLevel(): string
    {
        if ($this->accuracy_percentage >= 85) return 'Excellent';
        if ($this->accuracy_percentage >= 75) return 'Good';
        if ($this->accuracy_percentage >= 65) return 'Fair';
        return 'Poor';
    }

    public function isReliable(): bool
    {
        return $this->accuracy_percentage >= 70 && $this->sample_size >= 10;
    }

    // Static methods for analytics
    public static function getTopPerformers(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::orderByAccuracy()
            ->limit($limit)
            ->get();
    }

    public static function getStatTypePerformance(): array
    {
        return static::selectRaw('
            stat_type,
            AVG(accuracy_percentage) as avg_accuracy,
            COUNT(*) as test_count,
            MAX(accuracy_percentage) as best_accuracy,
            MIN(accuracy_percentage) as worst_accuracy
        ')
        ->groupBy('stat_type')
        ->orderByDesc('avg_accuracy')
        ->get()
        ->toArray();
    }

    public static function getPlayerRankings(?string $statType = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::selectRaw('
            player_id,
            player_name,
            player_position,
            stat_type,
            AVG(accuracy_percentage) as avg_accuracy,
            COUNT(*) as test_count,
            MAX(accuracy_percentage) as best_accuracy,
            AVG(sample_size) as avg_sample_size
        ')
        ->groupBy(['player_id', 'player_name', 'player_position', 'stat_type']);

        if ($statType) {
            $query->where('stat_type', $statType);
        }

        return $query->orderByDesc('avg_accuracy')
            ->havingRaw('COUNT(*) >= 1')
            ->get();
    }

    public static function getAccuracyTrends(int $days = 30): array
    {
        return static::selectRaw('
            DATE(tested_at) as test_date,
            AVG(accuracy_percentage) as avg_accuracy,
            COUNT(*) as test_count
        ')
        ->where('tested_at', '>=', now()->subDays($days))
        ->groupBy('test_date')
        ->orderBy('test_date')
        ->get()
        ->toArray();
    }
}
