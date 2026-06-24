<?php

namespace App\Services\Odds;

use App\Contracts\OddsProvider;

/**
 * Facade delegating to the configured OddsProvider implementation.
 */
class OddsService
{
    public function __construct(
        private OddsProvider $provider
    ) {}

    public function providerName(): string
    {
        return $this->provider->name();
    }

    public function getSports(): array
    {
        return $this->provider->getSports();
    }

    public function getOdds(
        string $sport = 'basketball_wnba',
        array $markets = ['h2h', 'spreads', 'totals'],
        ?array $bookmakers = null,
        string $region = 'us',
        string $oddsFormat = 'american'
    ): array {
        return $this->provider->getOdds($sport, $markets, $bookmakers, $region, $oddsFormat);
    }

    public function getPlayerProps(
        string $sport = 'basketball_wnba',
        array $markets = ['player_points', 'player_rebounds', 'player_assists'],
        ?array $bookmakers = null,
        string $region = 'us'
    ): array {
        return $this->provider->getPlayerProps($sport, $markets, $bookmakers, $region);
    }

    public function getWnbaPlayerProps(array $options = []): array
    {
        return $this->provider->getWnbaPlayerProps($options);
    }

    public function getWnbaEvents(): array
    {
        return $this->provider->getWnbaEvents();
    }

    public function getEvents(string $sport = 'basketball_wnba'): array
    {
        return $this->provider->getWnbaEvents();
    }

    public function getEventPlayerProps(string $eventId, array $options = []): array
    {
        $props = $this->provider->getWnbaPlayerProps($options);

        return array_values(array_filter($props, fn (array $prop) => ($prop['event_id'] ?? '') === $eventId));
    }

    public function getAvailablePlayerPropMarkets(): array
    {
        return config('odds-api.wnba_player_props', []);
    }

    public function getBestPlayerPropOdds(string $playerName, string $statType, $line = null): array
    {
        $playerProps = $this->provider->getWnbaPlayerProps([
            'player_name' => $playerName,
            'markets' => [$statType],
        ]);

        $bestOdds = [
            'over' => ['odds' => null, 'bookmaker' => null],
            'under' => ['odds' => null, 'bookmaker' => null],
        ];

        foreach ($playerProps as $prop) {
            if (($prop['stat_type'] ?? '') !== $statType) {
                continue;
            }
            if ($line !== null && ($prop['line'] ?? null) != $line) {
                continue;
            }
            foreach ($prop['bookmakers'] ?? [] as $bookmaker) {
                $name = strtolower($bookmaker['name'] ?? '');
                if (str_contains($name, 'over')) {
                    if ($bestOdds['over']['odds'] === null || $bookmaker['price'] > $bestOdds['over']['odds']) {
                        $bestOdds['over'] = ['odds' => $bookmaker['price'], 'bookmaker' => $bookmaker['bookmaker'] ?? 'consensus'];
                    }
                }
                if (str_contains($name, 'under')) {
                    if ($bestOdds['under']['odds'] === null || $bookmaker['price'] > $bestOdds['under']['odds']) {
                        $bestOdds['under'] = ['odds' => $bookmaker['price'], 'bookmaker' => $bookmaker['bookmaker'] ?? 'consensus'];
                    }
                }
            }
        }

        return $bestOdds;
    }

    public function getHistoricalOdds(string $sport, \Carbon\Carbon $date): array
    {
        return [];
    }

    public function getPlayerOdds(string $playerName, string $statType, string $sport = 'basketball_wnba'): ?array
    {
        return $this->provider->getPlayerOdds($playerName, $statType, $sport);
    }

    public function getUsageStats(): array
    {
        return $this->provider->getUsageStats();
    }

    public function clearCache(): void
    {
        $this->provider->clearCache();
    }
}
