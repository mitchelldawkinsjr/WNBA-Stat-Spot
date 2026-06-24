<?php

namespace App\Services\WNBA\Data;

use App\Models\WnbaPlayer;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use InvalidArgumentException;

class PlayerGamelogService
{
    public function __construct(
        private WnbaProviderResolver $resolver
    ) {}

    /**
     * @return array{provider: string, season: int, player_id: string, canonical_player_id: string, games: array<int, array<string, mixed>>}
     */
    public function fetch(string $playerId, int $season, ?int $lastNGames = null, ?string $providerOverride = null): array
    {
        $providerName = $this->resolver->resolveGamelogProviderName($playerId, $providerOverride);
        $canonicalPlayerId = $playerId;
        $providerPlayerId = $playerId;

        $player = WnbaPlayer::findByExternalId($playerId);
        if ($player) {
            $canonicalPlayerId = (string) $player->athlete_id;
            $providerPlayerId = match ($providerName) {
                'tank01' => (string) ($player->tank01_player_id ?? $player->athlete_id),
                default => (string) ($player->espn_athlete_id ?? $player->athlete_id),
            };
        }

        $games = match ($providerName) {
            'espn' => app(EspnWnbaProvider::class)->fetchPlayerGamelog($providerPlayerId, $season),
            'tank01' => app(Tank01WnbaProvider::class)->fetchPlayerGamelog($providerPlayerId, $season, $lastNGames),
            default => throw new InvalidArgumentException("Provider [{$providerName}] does not support player gamelog"),
        };

        if ($lastNGames !== null && $providerName === 'espn') {
            $games = array_slice($games, 0, $lastNGames);
        }

        return [
            'provider' => $providerName,
            'season' => $season,
            'player_id' => $playerId,
            'canonical_player_id' => $canonicalPlayerId,
            'games' => $games,
        ];
    }
}
