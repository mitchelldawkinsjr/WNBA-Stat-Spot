<?php

namespace App\Services\WNBA\Data;

use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use InvalidArgumentException;

class PlayerGamelogService
{
    public function __construct(
        private WnbaProviderResolver $resolver
    ) {}

    /**
     * @return array{provider: string, season: int, player_id: string, games: array<int, array<string, mixed>>}
     */
    public function fetch(string $playerId, int $season, ?int $lastNGames = null, ?string $providerOverride = null): array
    {
        $providerName = $this->resolver->resolveGamelogProviderName($playerId, $providerOverride);

        $games = match ($providerName) {
            'espn' => app(EspnWnbaProvider::class)->fetchPlayerGamelog($playerId, $season),
            'tank01' => app(Tank01WnbaProvider::class)->fetchPlayerGamelog($playerId, $season, $lastNGames),
            default => throw new InvalidArgumentException("Provider [{$providerName}] does not support player gamelog"),
        };

        if ($lastNGames !== null && $providerName === 'espn') {
            $games = array_slice($games, 0, $lastNGames);
        }

        return [
            'provider' => $providerName,
            'season' => $season,
            'player_id' => $playerId,
            'games' => $games,
        ];
    }
}
