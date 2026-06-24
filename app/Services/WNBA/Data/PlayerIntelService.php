<?php

namespace App\Services\WNBA\Data;

use App\Models\WnbaPlayer;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PlayerIntelService
{
    public function __construct(
        private EspnWnbaProvider $espn,
        private Tank01WnbaProvider $tank01,
    ) {}

    /**
     * @return array{
     *     provider: string,
     *     season: int,
     *     player_id: string,
     *     canonical_player_id: string,
     *     season_stats: array<string, mixed>|null,
     *     splits: array<int, array<string, mixed>>,
     *     news: array<int, array<string, mixed>>,
     *     injuries: array<int, array<string, mixed>>,
     *     next_game: array<string, mixed>|null,
     *     fantasy_outlook: string|null
     * }
     */
    public function overview(string $playerId, int $season): array
    {
        $resolved = $this->resolvePlayer($playerId);
        $overview = $this->fetchEspnOverview($resolved['espn_id'], $season);
        $injuries = $this->fetchEspnInjuries($resolved['espn_id']);

        if ($overview === null) {
            $overview = [
                'season_stats' => null,
                'splits' => [],
                'news' => [],
                'next_game' => null,
                'fantasy_outlook' => null,
            ];
        }

        if ($injuries === [] && $resolved['tank01_id'] !== null) {
            $injuries = $this->fetchTank01Injuries($resolved['tank01_id']);
        }

        if ($overview['news'] === [] && $resolved['tank01_id'] !== null) {
            $overview['news'] = $this->fetchTank01News($resolved['tank01_id'], 10);
        }

        if ($overview['season_stats'] === null && $resolved['tank01_id'] !== null) {
            $tankInfo = $this->fetchTank01PlayerInfo($resolved['tank01_id']);
            if ($tankInfo !== null) {
                $overview['season_stats'] = $tankInfo['season_stats'] ?? null;
            }
        }

        return [
            'provider' => 'espn',
            'season' => $season,
            'player_id' => $playerId,
            'canonical_player_id' => $resolved['canonical_id'],
            'season_stats' => $overview['season_stats'],
            'splits' => $overview['splits'],
            'news' => $overview['news'],
            'injuries' => $injuries,
            'next_game' => $overview['next_game'],
            'fantasy_outlook' => $overview['fantasy_outlook'],
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     season: int,
     *     player_id: string,
     *     canonical_player_id: string,
     *     season_stats: array<string, mixed>|null,
     *     splits: array<int, array<string, mixed>>
     * }
     */
    public function seasonStats(string $playerId, int $season): array
    {
        $resolved = $this->resolvePlayer($playerId);
        $overview = $this->fetchEspnOverview($resolved['espn_id'], $season);

        if ($overview === null && $resolved['tank01_id'] !== null) {
            $tankInfo = $this->fetchTank01PlayerInfo($resolved['tank01_id']);

            return [
                'provider' => 'tank01',
                'season' => $season,
                'player_id' => $playerId,
                'canonical_player_id' => $resolved['canonical_id'],
                'season_stats' => $tankInfo['season_stats'] ?? null,
                'splits' => [],
            ];
        }

        return [
            'provider' => 'espn',
            'season' => $season,
            'player_id' => $playerId,
            'canonical_player_id' => $resolved['canonical_id'],
            'season_stats' => $overview['season_stats'] ?? null,
            'splits' => $overview['splits'] ?? [],
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     player_id: string,
     *     canonical_player_id: string,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function news(string $playerId, ?int $limit = null): array
    {
        $resolved = $this->resolvePlayer($playerId);
        $items = [];

        $overview = $this->fetchEspnOverview($resolved['espn_id'], (int) config('wnba.seasons.current_season'));
        if ($overview !== null) {
            $items = $overview['news'];
        }

        if ($items === [] && $resolved['tank01_id'] !== null) {
            $items = $this->fetchTank01News($resolved['tank01_id'], $limit ?? 15);
        }

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        return [
            'provider' => $items !== [] && $resolved['tank01_id'] !== null && $overview === null ? 'tank01' : 'espn',
            'player_id' => $playerId,
            'canonical_player_id' => $resolved['canonical_id'],
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     player_id: string,
     *     canonical_player_id: string,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function injuries(string $playerId, ?int $daysBack = null): array
    {
        $resolved = $this->resolvePlayer($playerId);
        $items = $this->fetchEspnInjuries($resolved['espn_id']);
        $provider = 'espn';

        if ($items === [] && $resolved['tank01_id'] !== null) {
            $items = $this->fetchTank01Injuries($resolved['tank01_id'], $daysBack);
            $provider = 'tank01';
        }

        return [
            'provider' => $provider,
            'player_id' => $playerId,
            'canonical_player_id' => $resolved['canonical_id'],
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *     provider: string,
     *     items: array<int, array<string, mixed>>
     * }
     */
    public function leagueNews(?int $limit = 25, ?string $filter = null): array
    {
        try {
            $items = $this->espn->fetchLeagueNews($limit ?? 25);
        } catch (RuntimeException $e) {
            Log::warning('ESPN league news fetch failed', ['error' => $e->getMessage()]);
            $items = [];
        }

        if ($items === [] && config('tank01.api_key')) {
            try {
                $options = ['max_items' => $limit ?? 25];
                if ($filter === 'recent') {
                    $options['recent_news'] = true;
                } elseif ($filter === 'fantasy') {
                    $options['fantasy_news'] = true;
                } else {
                    $options['top_news'] = true;
                }

                $items = $this->tank01->fetchLeagueNews($options);

                return ['provider' => 'tank01', 'items' => $items];
            } catch (RuntimeException $e) {
                Log::warning('Tank01 league news fetch failed', ['error' => $e->getMessage()]);
            }
        }

        return ['provider' => 'espn', 'items' => $items];
    }

    /**
     * @return array{
     *     provider: string,
     *     season: array<string, mixed>|null,
     *     teams: array<int, array<string, mixed>>
     * }
     */
    public function leagueInjuries(): array
    {
        try {
            $payload = $this->espn->fetchLeagueInjuries();

            return array_merge(['provider' => 'espn'], $payload);
        } catch (RuntimeException $e) {
            Log::warning('ESPN league injuries fetch failed', ['error' => $e->getMessage()]);

            return [
                'provider' => 'espn',
                'season' => null,
                'teams' => [],
            ];
        }
    }

    /**
     * @return array{canonical_id: string, espn_id: string, tank01_id: string|null}
     */
    private function resolvePlayer(string $playerId): array
    {
        $player = WnbaPlayer::findByExternalId($playerId);
        $canonicalId = $player?->athlete_id ?? $playerId;

        return [
            'canonical_id' => (string) $canonicalId,
            'espn_id' => (string) ($player?->espn_athlete_id ?? $player?->athlete_id ?? $playerId),
            'tank01_id' => $player?->tank01_player_id ? (string) $player->tank01_player_id : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchEspnOverview(string $espnId, int $season): ?array
    {
        try {
            return $this->espn->fetchAthleteOverview($espnId, $season);
        } catch (RuntimeException $e) {
            Log::warning('ESPN athlete overview fetch failed', [
                'athlete_id' => $espnId,
                'season' => $season,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchEspnInjuries(string $espnId): array
    {
        try {
            return $this->espn->fetchPlayerInjuries($espnId);
        } catch (RuntimeException $e) {
            Log::warning('ESPN player injuries fetch failed', [
                'athlete_id' => $espnId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTank01Injuries(string $tank01Id, ?int $daysBack = null): array
    {
        if (! config('tank01.api_key')) {
            return [];
        }

        try {
            return $this->tank01->fetchPlayerInjuries($tank01Id, $daysBack);
        } catch (RuntimeException $e) {
            Log::warning('Tank01 player injuries fetch failed', [
                'player_id' => $tank01Id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTank01News(string $tank01Id, int $limit): array
    {
        if (! config('tank01.api_key')) {
            return [];
        }

        try {
            return $this->tank01->fetchPlayerNews($tank01Id, ['max_items' => $limit]);
        } catch (RuntimeException $e) {
            Log::warning('Tank01 player news fetch failed', [
                'player_id' => $tank01Id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTank01PlayerInfo(string $tank01Id): ?array
    {
        if (! config('tank01.api_key')) {
            return null;
        }

        try {
            return $this->tank01->fetchPlayerInfo($tank01Id);
        } catch (RuntimeException $e) {
            Log::warning('Tank01 player info fetch failed', [
                'player_id' => $tank01Id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
