<?php

namespace App\Services\RapidApi;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Tank01UsageTracker
{
    private array $config;

    public function __construct()
    {
        $this->config = config('tank01.rate_limit', []);
    }

    public function canMakeRequest(bool $essential = false): bool
    {
        if (empty(config('tank01.api_key'))) {
            return false;
        }

        $monthlyRequests = $this->getMonthlyRequests();
        $monthlyLimit = $this->getMonthlyLimit();
        $blockThreshold = $this->config['block_threshold'] ?? 0.95;

        if ($monthlyRequests >= ($monthlyLimit * $blockThreshold)) {
            if ($essential && $monthlyRequests < $monthlyLimit) {
                return true;
            }

            Log::warning('Tank01 monthly API limit nearly reached', [
                'monthly_requests' => $monthlyRequests,
                'monthly_limit' => $monthlyLimit,
            ]);

            return false;
        }

        $dailyRequests = $this->getDailyRequests();
        $dailyTarget = $this->config['daily_target'] ?? 30;

        if (! $essential && $dailyRequests >= $dailyTarget) {
            Log::info('Tank01 daily API target reached', [
                'daily_requests' => $dailyRequests,
                'daily_target' => $dailyTarget,
            ]);

            return false;
        }

        $burstKey = $this->burstKey();
        $burstCount = Cache::get($burstKey, 0);
        $burstLimit = $this->config['burst_limit'] ?? 3;

        if ($burstCount >= $burstLimit) {
            return false;
        }

        return true;
    }

    public function recordRequest(string $endpoint = ''): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $month = Carbon::now()->format('Y-m');

        Cache::increment("tank01_requests_day_{$today}", 1);
        Cache::increment("tank01_requests_month_{$month}", 1);

        $burstKey = $this->burstKey();
        Cache::increment($burstKey, 1);
        Cache::put($burstKey, Cache::get($burstKey, 1), $this->config['cooldown_period'] ?? 300);

        Cache::put('tank01_last_request', Carbon::now()->toISOString());
        if ($endpoint !== '') {
            Cache::put('tank01_last_endpoint', $endpoint);
        }

        $monthlyRequests = $this->getMonthlyRequests();
        $monthlyLimit = $this->getMonthlyLimit();
        $warnThreshold = $this->config['warn_threshold'] ?? 0.80;

        if ($monthlyRequests >= ($monthlyLimit * $warnThreshold)) {
            Log::warning('Tank01 API usage approaching monthly limit', [
                'monthly_requests' => $monthlyRequests,
                'monthly_limit' => $monthlyLimit,
                'endpoint' => $endpoint,
            ]);
        }
    }

    public function estimateCost(string $operation, int $count = 1): int
    {
        return match ($operation) {
            'teams_bulk' => 1,
            'scoreboard' => 1,
            'box_score' => max(1, $count),
            'betting_odds' => 1,
            'full_season_boxscores' => max(1, $count),
            default => max(1, $count),
        };
    }

    public function hasBudgetFor(string $operation, int $count = 1): bool
    {
        $cost = $this->estimateCost($operation, $count);
        $remaining = max(0, $this->getMonthlyLimit() - $this->getMonthlyRequests());

        return $remaining >= $cost;
    }

    public function getUsageStats(): array
    {
        $today = Carbon::today()->format('Y-m-d');
        $month = Carbon::now()->format('Y-m');

        $dailyRequests = $this->getDailyRequests();
        $monthlyRequests = $this->getMonthlyRequests();
        $monthlyLimit = $this->getMonthlyLimit();
        $dailyTarget = $this->config['daily_target'] ?? 30;

        return [
            'provider' => 'tank01',
            'requests_today' => $dailyRequests,
            'requests_this_month' => $monthlyRequests,
            'monthly_limit' => $monthlyLimit,
            'daily_target' => $dailyTarget,
            'monthly_usage_percent' => $monthlyLimit > 0 ? round(($monthlyRequests / $monthlyLimit) * 100, 1) : 0,
            'daily_usage_percent' => $dailyTarget > 0 ? round(($dailyRequests / $dailyTarget) * 100, 1) : 0,
            'last_request' => Cache::get('tank01_last_request'),
            'last_endpoint' => Cache::get('tank01_last_endpoint'),
            'can_make_request' => $this->canMakeRequest(),
            'requests_remaining_today' => max(0, $dailyTarget - $dailyRequests),
            'requests_remaining_month' => max(0, $monthlyLimit - $monthlyRequests),
            'status' => $this->getUsageStatus($monthlyRequests, $monthlyLimit, $dailyRequests, $dailyTarget),
        ];
    }

    public function getMonthlyRequests(): int
    {
        $month = Carbon::now()->format('Y-m');

        return (int) Cache::get("tank01_requests_month_{$month}", 0);
    }

    public function getDailyRequests(): int
    {
        $today = Carbon::today()->format('Y-m-d');

        return (int) Cache::get("tank01_requests_day_{$today}", 0);
    }

    public function getMonthlyLimit(): int
    {
        return (int) ($this->config['requests_per_month'] ?? 1000);
    }

    private function burstKey(): string
    {
        return 'tank01_burst_'.Carbon::now()->format('Y-m-d-H-i');
    }

    private function getUsageStatus(int $monthly, int $monthlyLimit, int $daily, int $dailyTarget): string
    {
        if ($monthlyLimit > 0 && $monthly >= ($monthlyLimit * ($this->config['block_threshold'] ?? 0.95))) {
            return 'blocked';
        }
        if ($monthlyLimit > 0 && $monthly >= ($monthlyLimit * ($this->config['warn_threshold'] ?? 0.80))) {
            return 'warning';
        }
        if ($daily >= $dailyTarget) {
            return 'daily_target_reached';
        }

        return 'healthy';
    }
}
