<?php

namespace App\Services\WNBA\Data\Support;

class TeamCatalog
{
    /**
     * Map provider-specific abbreviations to ESPN canonical abbreviations.
     *
     * @return array<string, string>
     */
    public static function abbreviationAliases(): array
    {
        return config('wnba.teams.abbreviation_aliases', []);
    }

    /**
     * @return list<string>
     */
    public static function excludedTeamIds(): array
    {
        return config('wnba.teams.excluded_team_ids', []);
    }

    public static function canonicalAbbreviation(string $abbreviation): string
    {
        $abbr = strtoupper(trim($abbreviation));
        if ($abbr === '') {
            return '';
        }

        return self::abbreviationAliases()[$abbr] ?? $abbr;
    }

    /**
     * @return list<string>
     */
    public static function aliasesFor(string $canonicalAbbreviation): array
    {
        $canonical = strtoupper(trim($canonicalAbbreviation));
        $aliases = [$canonical];

        foreach (self::abbreviationAliases() as $alias => $target) {
            if ($target === $canonical) {
                $aliases[] = $alias;
            }
        }

        return array_values(array_unique($aliases));
    }

    public static function isLeagueTeamId(string $teamId): bool
    {
        return ! in_array($teamId, self::excludedTeamIds(), true);
    }

    /**
     * Season types that count toward primary league stats.
     * 2 = Regular Season, 3 = Playoffs, 4 = Finals.
     * Preseason (1) and All-Star (5) are secondary.
     *
     * @return list<int>
     */
    public static function primarySeasonTypes(): array
    {
        return [2, 3, 4];
    }

    /**
     * Restrict a query (already joining wnba_games as g) to league competition:
     * regular season / playoffs / finals, and non-exhibition team_ids.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function wherePrimaryCompetition($query, string $teamIdColumn): void
    {
        $query->whereIn('g.season_type', self::primarySeasonTypes());

        $excluded = self::excludedTeamIds();
        if ($excluded !== []) {
            $query->whereNotIn($teamIdColumn, $excluded);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function whereLeagueTeam($query, string $teamIdColumn): void
    {
        $excluded = self::excludedTeamIds();
        if ($excluded !== []) {
            $query->whereNotIn($teamIdColumn, $excluded);
        }
    }

    public static function teamMatchKey(?string $location, ?string $name): string
    {
        return self::normalizeToken($location).self::normalizeToken($name);
    }

    /**
     * @param  array<string, mixed>  $espnTeam
     * @param  array<int, array<string, mixed>>  $tank01Teams
     * @return array<string, mixed>|null
     */
    public static function matchTank01Team(array $espnTeam, array $tank01Teams): ?array
    {
        $espnAbv = self::canonicalAbbreviation((string) ($espnTeam['team_abbreviation'] ?? ''));

        foreach ($tank01Teams as $team) {
            $tankAbv = self::canonicalAbbreviation((string) ($team['team_abbreviation'] ?? ''));
            if ($espnAbv !== '' && $tankAbv === $espnAbv) {
                return $team;
            }
        }

        $espnKey = self::teamMatchKey(
            (string) ($espnTeam['team_location'] ?? ''),
            (string) ($espnTeam['team_name'] ?? ''),
        );

        if ($espnKey === '') {
            return null;
        }

        foreach ($tank01Teams as $team) {
            $tankKey = self::teamMatchKey(
                (string) ($team['team_location'] ?? ''),
                (string) ($team['team_name'] ?? ''),
            );

            if ($tankKey === $espnKey) {
                return $team;
            }
        }

        return null;
    }

    private static function normalizeToken(?string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]/i', '', (string) $value) ?? ''));
    }
}
