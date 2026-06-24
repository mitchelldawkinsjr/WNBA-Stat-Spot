<?php

namespace App\Contracts;

interface OddsProvider
{
    public function name(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSports(): array;

    /**
     * @param  array<int, string>  $markets
     * @param  array<int, string>|null  $bookmakers
     * @return array<int, array<string, mixed>>
     */
    public function getOdds(
        string $sport = 'basketball_wnba',
        array $markets = ['h2h', 'spreads', 'totals'],
        ?array $bookmakers = null,
        string $region = 'us',
        string $oddsFormat = 'american'
    ): array;

    /**
     * @param  array<int, string>  $markets
     * @return array<int, array<string, mixed>>
     */
    public function getPlayerProps(
        string $sport = 'basketball_wnba',
        array $markets = ['player_points', 'player_rebounds', 'player_assists'],
        ?array $bookmakers = null,
        string $region = 'us'
    ): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function getWnbaPlayerProps(array $options = []): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWnbaEvents(): array;

    public function getPlayerOdds(string $playerName, string $statType, string $sport = 'basketball_wnba'): ?array;

    /**
     * @return array<string, mixed>
     */
    public function getUsageStats(): array;

    public function clearCache(): void;
}
