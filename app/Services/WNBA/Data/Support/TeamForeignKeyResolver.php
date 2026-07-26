<?php

namespace App\Services\WNBA\Data\Support;

use App\Models\WnbaTeam;

final class TeamForeignKeyResolver
{
    /**
     * External/provider values that may appear in wnba_game_teams.team_id /
     * wnba_player_games.team_id. Never include the Eloquent primary key — numeric
     * ESPN ids (e.g. LV "17") collide with unrelated teams' autoincrement ids.
     *
     * @return list<string>
     */
    public static function foreignKeysForTeam(WnbaTeam $team): array
    {
        return array_values(array_unique(array_filter([
            (string) $team->team_id,
            (string) $team->espn_team_id,
            (string) $team->tank01_team_id,
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

    /**
     * Resolve by external provider ids first. Primary-key lookup is last-resort
     * only (UI sometimes passes wnba_teams.id from API responses).
     */
    public static function resolveTeam(string|int $teamReference): ?WnbaTeam
    {
        $reference = (string) $teamReference;
        if ($reference === '') {
            return null;
        }

        $byExternal = WnbaTeam::query()
            ->where('team_id', $reference)
            ->orWhere('espn_team_id', $reference)
            ->orWhere('tank01_team_id', $reference)
            ->first();

        if ($byExternal !== null) {
            return $byExternal;
        }

        if (ctype_digit($reference)) {
            return WnbaTeam::query()->find((int) $reference);
        }

        return null;
    }
}
