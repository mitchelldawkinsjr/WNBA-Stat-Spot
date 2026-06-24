<?php

namespace App\Services\Odds\Providers;

use App\Contracts\OddsProvider;
use App\Services\Odds\OddsApiService;

class OddsApiProvider implements OddsProvider
{
    public function __construct(
        private OddsApiService $oddsApi
    ) {}

    public function name(): string
    {
        return 'odds_api';
    }

    public function getSports(): array
    {
        return $this->oddsApi->getSports();
    }

    public function getOdds(
        string $sport = 'basketball_wnba',
        array $markets = ['h2h', 'spreads', 'totals'],
        ?array $bookmakers = null,
        string $region = 'us',
        string $oddsFormat = 'american'
    ): array {
        return $this->oddsApi->getOdds($sport, $markets, $bookmakers, $region, $oddsFormat);
    }

    public function getPlayerProps(
        string $sport = 'basketball_wnba',
        array $markets = ['player_points', 'player_rebounds', 'player_assists'],
        ?array $bookmakers = null,
        string $region = 'us'
    ): array {
        return $this->oddsApi->getPlayerProps($sport, $markets, $bookmakers, $region);
    }

    public function getWnbaPlayerProps(array $options = []): array
    {
        return $this->oddsApi->getWnbaPlayerProps($options);
    }

    public function getWnbaEvents(): array
    {
        return $this->oddsApi->getWnbaEvents();
    }

    public function getPlayerOdds(string $playerName, string $statType, string $sport = 'basketball_wnba'): ?array
    {
        return $this->oddsApi->getPlayerOdds($playerName, $statType, $sport);
    }

    public function getUsageStats(): array
    {
        return $this->oddsApi->getUsageStats();
    }

    public function clearCache(): void
    {
        $this->oddsApi->clearCache();
    }
}
