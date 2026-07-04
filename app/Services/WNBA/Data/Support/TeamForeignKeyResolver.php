<?php

namespace App\Services\WNBA\Data\Support;

use App\Models\WnbaTeam;

final class TeamForeignKeyResolver
{
    /**
     * Values that may appear in wnba_game_teams.team_id / wnba_player_games.team_id.
     *
     * @return list<string>
     */
    public static function foreignKeysForTeam(WnbaTeam $team): array
    {
        return array_values(array_unique(array_filter([
            (string) $team->team_id,
            (string) $team->espn_team_id,
            (string) $team->tank01_team_id,
            (string) $team->id,
        ], static fn (string $value): bool => $value !== '')));
    }

    /**
     * @return list<string>
     */
    public static function foreignKeysForReference(string|int $teamReference): array
    {
        $team = self::resolveTeam($teamReference);

        return $team !== null
            ? self::foreignKeysForTeam($team)
            : [(string) $teamReference];
    }

    public static function resolveTeam(string|int $teamReference): ?WnbaTeam
    {
        if (is_int($teamReference) || (is_string($teamReference) && ctype_digit($teamReference))) {
            $byPrimaryKey = WnbaTeam::query()->find((int) $teamReference);
            if ($byPrimaryKey !== null) {
                return $byPrimaryKey;
            }
        }

        $reference = (string) $teamReference;

        return WnbaTeam::query()
            ->where('team_id', $reference)
            ->orWhere('espn_team_id', $reference)
            ->orWhere('tank01_team_id', $reference)
            ->first();
    }
}
