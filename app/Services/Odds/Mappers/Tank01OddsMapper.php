<?php

namespace App\Services\Odds\Mappers;

class Tank01OddsMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $games
     * @return array<int, array<string, mixed>>
     */
    public function mapToEvents(array $games): array
    {
        $events = [];

        foreach ($games as $game) {
            $gameId = $game['gameID'] ?? '';
            $events[] = [
                'id' => $gameId,
                'sport_key' => 'basketball_wnba',
                'sport_title' => 'WNBA',
                'commence_time' => $this->commenceTime($game),
                'home_team' => $game['home'] ?? '',
                'away_team' => $game['away'] ?? '',
                'bookmakers' => $this->mapBookmakers($game),
            ];
        }

        return $events;
    }

    /**
     * @param  array<int, array<string, mixed>>  $games
     * @return array<int, array<string, mixed>>
     */
    public function mapToPlayerProps(array $games): array
    {
        $props = [];

        foreach ($games as $game) {
            $gameId = $game['gameID'] ?? '';
            $home = $game['home'] ?? '';
            $away = $game['away'] ?? '';
            $commence = $this->commenceTime($game);

            foreach (($game['playerProps'] ?? []) as $playerId => $player) {
                foreach (($player['props'] ?? []) as $statType => $line) {
                    $props[] = [
                        'player_name' => $player['longName'] ?? (string) $playerId,
                        'stat_type' => $this->normalizeStatType($statType),
                        'line' => (float) ($line['line'] ?? 0),
                        'event_id' => $gameId,
                        'commence_time' => $commence,
                        'home_team' => $home,
                        'away_team' => $away,
                        'bookmakers' => $this->mapPlayerPropBookmakers($line),
                    ];
                }
            }
        }

        return $props;
    }

    /**
     * @param  array<string, mixed>  $game
     * @return array<int, array<string, mixed>>
     */
    private function mapBookmakers(array $game): array
    {
        $bookmakers = [];

        foreach (($game['sportsbooks'] ?? []) as $key => $data) {
            $markets = [];

            if (isset($data['moneyline'])) {
                $markets[] = [
                    'key' => 'h2h',
                    'outcomes' => [
                        ['name' => $game['away'] ?? 'Away', 'price' => $this->americanToInt($data['moneyline']['away'] ?? 0)],
                        ['name' => $game['home'] ?? 'Home', 'price' => $this->americanToInt($data['moneyline']['home'] ?? 0)],
                    ],
                ];
            }

            if (isset($data['spread'])) {
                $markets[] = [
                    'key' => 'spreads',
                    'outcomes' => [
                        [
                            'name' => $game['away'] ?? 'Away',
                            'price' => $this->americanToInt($data['spread']['awayOdds'] ?? 0),
                            'point' => (float) str_replace('+', '', (string) ($data['spread']['away'] ?? 0)),
                        ],
                        [
                            'name' => $game['home'] ?? 'Home',
                            'price' => $this->americanToInt($data['spread']['homeOdds'] ?? 0),
                            'point' => (float) str_replace('+', '', (string) ($data['spread']['home'] ?? 0)),
                        ],
                    ],
                ];
            }

            if (isset($data['total'])) {
                $point = (float) preg_replace('/[^0-9.]/', '', (string) ($data['total']['over'] ?? '0'));
                $markets[] = [
                    'key' => 'totals',
                    'outcomes' => [
                        ['name' => 'Over', 'price' => $this->americanToInt($data['total']['overOdds'] ?? 0), 'point' => $point],
                        ['name' => 'Under', 'price' => $this->americanToInt($data['total']['underOdds'] ?? 0), 'point' => $point],
                    ],
                ];
            }

            $bookmakers[] = [
                'key' => $key,
                'title' => ucfirst($key),
                'last_update' => now()->toISOString(),
                'markets' => $markets,
            ];
        }

        return $bookmakers;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<int, array<string, mixed>>
     */
    private function mapPlayerPropBookmakers(array $line): array
    {
        $bookmakers = [];

        if (isset($line['over'])) {
            $bookmakers[] = [
                'bookmaker' => 'consensus',
                'bookmaker_key' => 'consensus',
                'name' => 'Over',
                'price' => $this->americanToInt($line['over']),
                'point' => (float) ($line['line'] ?? 0),
                'last_update' => now()->toISOString(),
            ];
        }

        if (isset($line['under'])) {
            $bookmakers[] = [
                'bookmaker' => 'consensus',
                'bookmaker_key' => 'consensus',
                'name' => 'Under',
                'price' => $this->americanToInt($line['under']),
                'point' => (float) ($line['line'] ?? 0),
                'last_update' => now()->toISOString(),
            ];
        }

        return $bookmakers;
    }

    /**
     * @param  array<string, mixed>  $game
     */
    private function commenceTime(array $game): string
    {
        $raw = $game['gameDate'] ?? '';
        if (strlen((string) $raw) === 8 && ctype_digit((string) $raw)) {
            return substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2).'T00:00:00Z';
        }

        return now()->toISOString();
    }

    private function americanToInt(mixed $odds): int
    {
        if (is_int($odds)) {
            return $odds;
        }

        $odds = trim((string) $odds);
        if ($odds === '') {
            return 0;
        }

        return (int) $odds;
    }

    private function normalizeStatType(string $statType): string
    {
        return match ($statType) {
            'points' => 'player_points',
            'rebounds' => 'player_rebounds',
            'assists' => 'player_assists',
            'threes' => 'player_threes',
            default => 'player_'.$statType,
        };
    }
}
