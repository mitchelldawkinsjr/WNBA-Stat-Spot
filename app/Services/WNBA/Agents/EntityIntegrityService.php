<?php

namespace App\Services\WNBA\Agents;

use App\Models\WnbaDataConflict;
use App\Models\WnbaGame;
use App\Models\WnbaPlayer;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\Support\TeamCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Entity Integrity Agent core: audits team and player correctness — orphaned
 * stat rows, duplicate entities, identity-mapping gaps, metadata problems.
 * Findings are written to wnba_data_conflicts as review-queue items; nothing
 * destructive is ever auto-fixed.
 */
class EntityIntegrityService
{
    private ?AgentRunReporter $reporter = null;

    public function setReporter(?AgentRunReporter $reporter): void
    {
        $this->reporter = $reporter;
    }

    /**
     * @return array<string, int> findings per check
     */
    public function audit(?int $season = null): array
    {
        $findings = [
            'orphan_player_games' => $this->auditOrphanPlayerGames(),
            'orphan_game_teams' => $this->auditOrphanGameTeams(),
            'duplicate_players' => $this->auditDuplicatePlayers(),
            'duplicate_games' => $this->auditDuplicateGames($season),
            'mapping_gaps' => $this->auditMappingGaps($season),
            'metadata_gaps' => $this->auditMetadataGaps($season),
            'unknown_abbreviations' => $this->auditUnknownAbbreviations(),
            'multi_team_players' => $this->auditMultiTeamPlayers($season),
        ];

        foreach ($findings as $check => $count) {
            $this->reporter?->set("findings_{$check}", $count);
        }

        return $findings;
    }

    private function auditOrphanPlayerGames(): int
    {
        $orphans = DB::table('wnba_player_games as pg')
            ->leftJoin('wnba_players as p', 'p.id', '=', 'pg.player_id')
            ->leftJoin('wnba_teams as t', 't.team_id', '=', 'pg.team_id')
            ->leftJoin('wnba_games as g', 'g.id', '=', 'pg.game_id')
            ->where(function ($query) {
                $query->whereNull('p.id')->orWhereNull('t.id')->orWhereNull('g.id');
            })
            ->select('pg.id', 'pg.player_id', 'pg.team_id', 'pg.game_id')
            ->limit(200)
            ->get();

        foreach ($orphans as $row) {
            $this->flag(
                'player_game',
                (string) $row->id,
                'orphan_reference',
                "player_games row {$row->id} references missing player/team/game "
                    ."(player_id={$row->player_id}, team_id={$row->team_id}, game_id={$row->game_id})"
            );
        }

        return $orphans->count();
    }

    private function auditOrphanGameTeams(): int
    {
        $orphans = DB::table('wnba_game_teams as gt')
            ->leftJoin('wnba_teams as t', 't.team_id', '=', 'gt.team_id')
            ->leftJoin('wnba_teams as ot', 'ot.team_id', '=', 'gt.opponent_team_id')
            ->leftJoin('wnba_games as g', 'g.id', '=', 'gt.game_id')
            ->where(function ($query) {
                $query->whereNull('t.id')->orWhereNull('ot.id')->orWhereNull('g.id');
            })
            ->select('gt.id', 'gt.team_id', 'gt.opponent_team_id', 'gt.game_id')
            ->limit(200)
            ->get();

        foreach ($orphans as $row) {
            $this->flag(
                'game_team',
                (string) $row->id,
                'orphan_reference',
                "game_teams row {$row->id} references missing team/opponent/game"
            );
        }

        return $orphans->count();
    }

    /**
     * Same normalized display name under two canonical IDs. Name similarity is
     * never grounds for auto-merge — these always go to review.
     */
    private function auditDuplicatePlayers(): int
    {
        $players = WnbaPlayer::query()
            ->get(['id', 'athlete_id', 'athlete_display_name'])
            ->groupBy(fn ($player) => $this->normalizeName((string) $player->athlete_display_name))
            ->filter(fn ($group, $name) => $name !== '' && $name !== 'unknownplayer' && $group->count() > 1);

        foreach ($players as $name => $group) {
            $ids = $group->pluck('athlete_id')->map(fn ($id) => (string) $id)->all();
            $this->flag(
                'player',
                implode(',', $group->pluck('id')->all()),
                'possible_duplicate',
                'players share normalized name "'.$name.'" under athlete_ids ['.implode(', ', $ids).']'
            );
        }

        return $players->count();
    }

    private function auditDuplicateGames(?int $season): int
    {
        $query = WnbaGame::query()
            ->join('wnba_game_teams as gt', function ($join) {
                $join->on('gt.game_id', '=', 'wnba_games.id')->where('gt.home_away', 'home');
            })
            ->select('wnba_games.id', 'wnba_games.game_id', 'wnba_games.game_date', 'gt.team_id', 'gt.opponent_team_id');

        if ($season !== null) {
            $query->where('wnba_games.season', $season);
        }

        $duplicates = $query->get()
            ->groupBy(fn ($game) => $game->game_date.'|'.$game->team_id.'|'.$game->opponent_team_id)
            ->filter(fn ($group) => $group->count() > 1);

        foreach ($duplicates as $key => $group) {
            $this->flag(
                'game',
                implode(',', $group->pluck('game_id')->all()),
                'possible_duplicate',
                "multiple game rows for the same date and team pair ({$key})"
            );
        }

        return $duplicates->count();
    }

    /**
     * Cross-provider mapping gaps for entities that have stats. Reported, not
     * auto-fixed: EntityMergeService sync is the safe fixer and runs separately.
     */
    private function auditMappingGaps(?int $season): int
    {
        $playersQuery = WnbaPlayer::query()
            ->whereHas('playerGames', function ($query) use ($season) {
                if ($season !== null) {
                    $query->whereHas('game', fn ($gameQuery) => $gameQuery->where('season', $season));
                }
            })
            ->where(function ($query) {
                $query->whereNull('espn_athlete_id')->orWhereNull('tank01_player_id');
            });

        $count = $playersQuery->count();
        if ($count > 0) {
            $this->reporter?->warn("{$count} players with game stats are missing an ESPN or Tank01 identity mapping (run app:sync-entity-identities)");
        }

        // Two players claiming the same external ID is impossible via the unique
        // constraints, but athlete_id colliding with another player's provider id
        // is not.
        $collisions = DB::table('wnba_players as a')
            ->join('wnba_players as b', function ($join) {
                $join->on('a.athlete_id', '=', 'b.tank01_player_id')->whereColumn('a.id', '!=', 'b.id');
            })
            ->select('a.id as a_id', 'b.id as b_id', 'a.athlete_id')
            ->get();

        foreach ($collisions as $row) {
            $this->flag(
                'player',
                "{$row->a_id},{$row->b_id}",
                'identity_collision',
                "athlete_id {$row->athlete_id} of player {$row->a_id} is claimed as tank01_player_id by player {$row->b_id}"
            );
        }

        return $count + $collisions->count();
    }

    private function auditMetadataGaps(?int $season): int
    {
        $flagged = 0;

        $unknownPlayers = WnbaPlayer::query()
            ->where('athlete_display_name', 'Unknown Player')
            ->whereHas('playerGames', function ($query) use ($season) {
                if ($season !== null) {
                    $query->whereHas('game', fn ($gameQuery) => $gameQuery->where('season', $season));
                }
            })
            ->count();

        if ($unknownPlayers > 0) {
            $this->reporter?->warn("{$unknownPlayers} players with game stats have placeholder name 'Unknown Player'");
            $flagged += $unknownPlayers;
        }

        $unknownTeams = WnbaTeam::query()->where('team_abbreviation', 'UNK')->get(['id', 'team_id']);
        foreach ($unknownTeams as $team) {
            $this->flag(
                'team',
                (string) $team->team_id,
                'placeholder_metadata',
                "team {$team->team_id} has placeholder abbreviation UNK"
            );
            $flagged++;
        }

        return $flagged;
    }

    private function auditUnknownAbbreviations(): int
    {
        $canonical = WnbaTeam::query()
            ->league()
            ->pluck('team_abbreviation')
            ->map(fn ($abbr) => strtoupper((string) $abbr))
            ->unique();

        $aliases = array_map('strtoupper', array_keys(TeamCatalog::abbreviationAliases()));
        $known = $canonical->merge($aliases)->merge(['UNK'])->all();

        $unknown = WnbaTeam::query()
            ->league()
            ->get(['team_id', 'team_abbreviation'])
            ->filter(function ($team) use ($known) {
                $abbr = strtoupper((string) $team->team_abbreviation);

                return $abbr !== '' && ! in_array(TeamCatalog::canonicalAbbreviation($abbr), $known, true);
            });

        foreach ($unknown as $team) {
            $this->flag(
                'team',
                (string) $team->team_id,
                'unrecognized_abbreviation',
                "abbreviation {$team->team_abbreviation} is not canonical and has no TeamCatalog alias"
            );
        }

        return $unknown->count();
    }

    /**
     * Players appearing for multiple teams in one season: legitimate for
     * trades, but surfaced so stat attribution can be verified.
     */
    private function auditMultiTeamPlayers(?int $season): int
    {
        $query = DB::table('wnba_player_games as pg')
            ->join('wnba_games as g', 'g.id', '=', 'pg.game_id')
            ->select('pg.player_id', DB::raw('COUNT(DISTINCT pg.team_id) as team_count'));

        if ($season !== null) {
            $query->where('g.season', $season);
        }

        $rows = $query->groupBy('pg.player_id')->havingRaw('COUNT(DISTINCT pg.team_id) > 1')->get();

        if ($rows->isNotEmpty()) {
            $this->reporter?->warn($rows->count().' players have stats for multiple teams this season (verify trades vs mis-attribution)');
        }

        return $rows->count();
    }

    private function normalizeName(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $name) ?? '');
    }

    /**
     * Creates a review-queue finding unless the identical open finding exists.
     */
    private function flag(string $entityType, string $entityKey, string $field, string $reason): void
    {
        $exists = WnbaDataConflict::query()
            ->where('entity_type', $entityType)
            ->where('entity_key', $entityKey)
            ->where('field', $field)
            ->where('requires_review', true)
            ->whereNull('resolved_at')
            ->exists();

        if ($exists) {
            return;
        }

        WnbaDataConflict::create([
            'entity_type' => $entityType,
            'entity_key' => $entityKey,
            'field' => $field,
            'candidates' => [],
            'resolution_reason' => $reason,
            'requires_review' => true,
            'agent_run_id' => $this->reporter?->runId(),
        ]);

        $this->reporter?->increment('review_queue_added');
    }
}
