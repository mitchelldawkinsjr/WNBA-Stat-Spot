<?php

namespace App\Services\WNBA\Agents;

use App\Models\WnbaDataConflict;
use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Data\Mappers\WnbaStatsMapper;
use App\Services\WNBA\Data\Support\TeamCatalog;
use App\Services\WNBA\Data\Support\WnbaStatsApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Verification Agent: read-only reconciliation of our final box scores against
 * the league stats API (stats.wnba.com). Findings go to the review queue;
 * canonical tables are never overwritten.
 */
class VerificationService
{
    public const SOURCE_ID = 'wnba_stats';

    /** @var list<string> */
    private const PLAYER_STAT_FIELDS = [
        'points',
        'rebounds',
        'offensive_rebounds',
        'defensive_rebounds',
        'assists',
        'steals',
        'blocks',
        'turnovers',
        'fouls',
        'field_goals_made',
        'field_goals_attempted',
        'three_point_field_goals_made',
        'three_point_field_goals_attempted',
        'free_throws_made',
        'free_throws_attempted',
        'plus_minus',
    ];

    /** @var list<string> */
    private const TEAM_STAT_FIELDS = [
        'team_score',
        'field_goals_made',
        'field_goals_attempted',
        'three_point_field_goals_made',
        'three_point_field_goals_attempted',
        'free_throws_made',
        'free_throws_attempted',
        'offensive_rebounds',
        'defensive_rebounds',
        'rebounds',
        'assists',
        'steals',
        'blocks',
        'turnovers',
        'fouls',
    ];

    private ?AgentRunReporter $reporter = null;

    private bool $dryRun = false;

    public function __construct(
        private readonly WnbaStatsApiClient $client,
        private readonly WnbaStatsMapper $mapper,
        private readonly RawPayloadStore $rawPayloads,
        private readonly BoxScoreValidator $minutesParser,
    ) {}

    public function setReporter(?AgentRunReporter $reporter): void
    {
        $this->reporter = $reporter;
    }

    /**
     * @return array<string, int>
     */
    public function verify(?int $season = null, bool $fullSeason = false, bool $dryRun = false, ?int $lookbackDays = null): array
    {
        $this->dryRun = $dryRun;
        $season ??= (int) config('wnba.seasons.current_season');
        $lookback = $fullSeason
            ? null
            : ($lookbackDays ?? (int) config('wnba.agents.verification.lookback_days', 3));

        $findings = [
            'games_checked' => 0,
            'games_unmapped' => 0,
            'games_fetch_failed' => 0,
            'stat_mismatches' => 0,
            'players_unmapped' => 0,
            'players_missing' => 0,
            'ids_linked' => 0,
        ];

        $scheduleIndex = $this->loadScheduleIndex();
        $gameTeamsByPk = $this->loadGameTeamSides($season, $lookback);
        $games = $this->finalGamesToVerify($season, $lookback);

        foreach ($games as $game) {
            $oracleGameId = $this->resolveOracleGameId($game, $scheduleIndex, $gameTeamsByPk);
            if ($oracleGameId === null) {
                $findings['games_unmapped']++;
                $this->flag(
                    'game',
                    (string) $game->game_id,
                    'oracle_game_unmapped',
                    "no unique stats.wnba.com match for game {$game->game_id} on {$game->game_date}",
                    []
                );

                continue;
            }

            try {
                $payload = $this->client->boxScoreTraditional($oracleGameId);
            } catch (\Throwable $e) {
                $findings['games_fetch_failed']++;
                $this->reporter?->error("oracle fetch failed for {$oracleGameId}: {$e->getMessage()}");
                $this->flag(
                    'game',
                    (string) $game->game_id,
                    'oracle_fetch_failed',
                    "failed to fetch stats.wnba.com box for {$oracleGameId}: {$e->getMessage()}",
                    [['source_id' => self::SOURCE_ID, 'value' => $oracleGameId]]
                );

                continue;
            }

            if (! $this->dryRun) {
                $this->rawPayloads->store(
                    self::SOURCE_ID,
                    'boxscore',
                    $payload,
                    $season,
                    "boxscoretraditionalv3:{$oracleGameId}"
                );
            }

            $mapped = $this->mapper->mapBoxScoreTraditional($payload);
            $gameFindings = $this->diffGame($game, $mapped);
            foreach ($gameFindings as $key => $count) {
                $findings[$key] = ($findings[$key] ?? 0) + $count;
            }
            $findings['games_checked']++;
        }

        foreach ($findings as $check => $count) {
            $this->reporter?->set("findings_{$check}", $count);
        }

        if ($findings['stat_mismatches'] > 0 || $findings['games_unmapped'] > 0) {
            $this->reporter?->warn(sprintf(
                'verification found %d stat mismatches and %d unmapped games',
                $findings['stat_mismatches'],
                $findings['games_unmapped']
            ));
        }

        return $findings;
    }

    /**
     * @return Collection<int, WnbaGame>
     */
    private function finalGamesToVerify(int $season, ?int $lookbackDays): Collection
    {
        $query = WnbaGame::query()
            ->where('season', $season)
            ->where(function ($q) {
                $q->where('status_type', 'post')
                    ->orWhere('status_name', 'like', '%FINAL%');
            })
            ->orderBy('game_date');

        if ($lookbackDays !== null) {
            $query->whereDate('game_date', '>=', now()->subDays($lookbackDays)->toDateString());
        }

        return $query->get();
    }

    /**
     * @return array<string, list<array{game_id: string, game_date: string, home_tricode: string, away_tricode: string, home_team_id: string|null, away_team_id: string|null}>>
     */
    private function loadScheduleIndex(): array
    {
        try {
            $payload = $this->client->schedule();
            if (! $this->dryRun) {
                $this->rawPayloads->store(
                    self::SOURCE_ID,
                    'schedule',
                    $payload,
                    (int) config('wnba.seasons.current_season'),
                    'scheduleLeagueV2'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Verification schedule fetch failed', ['error' => $e->getMessage()]);
            $this->reporter?->error('oracle schedule fetch failed: '.$e->getMessage());

            return [];
        }

        $index = [];
        foreach ($this->mapper->mapSchedule($payload) as $row) {
            $key = $this->scheduleKey($row['game_date'], $row['home_tricode'], $row['away_tricode']);
            $index[$key][] = $row;
        }

        return $index;
    }

    /**
     * @return array<int, array{home: string|null, away: string|null, home_team_id: string|null, away_team_id: string|null}>
     */
    private function loadGameTeamSides(int $season, ?int $lookbackDays): array
    {
        $query = WnbaGameTeam::query()
            ->select('wnba_game_teams.*', 'wnba_teams.team_abbreviation', 'wnba_teams.wnba_stats_team_id')
            ->join('wnba_games', 'wnba_games.id', '=', 'wnba_game_teams.game_id')
            ->leftJoin('wnba_teams', 'wnba_teams.team_id', '=', 'wnba_game_teams.team_id')
            ->where('wnba_games.season', $season);

        if ($lookbackDays !== null) {
            $query->whereDate('wnba_games.game_date', '>=', now()->subDays($lookbackDays)->toDateString());
        }

        $byPk = [];
        foreach ($query->get() as $row) {
            $pk = (int) $row->game_id;
            $abbr = TeamCatalog::canonicalAbbreviation((string) ($row->team_abbreviation ?? ''));
            $byPk[$pk] ??= ['home' => null, 'away' => null, 'home_team_id' => null, 'away_team_id' => null];
            if ($row->home_away === 'home') {
                $byPk[$pk]['home'] = $abbr;
                $byPk[$pk]['home_team_id'] = $row->team_id;
            } else {
                $byPk[$pk]['away'] = $abbr;
                $byPk[$pk]['away_team_id'] = $row->team_id;
            }
        }

        return $byPk;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $scheduleIndex
     * @param  array<int, array{home: string|null, away: string|null, home_team_id: string|null, away_team_id: string|null}>  $gameTeamsByPk
     */
    private function resolveOracleGameId(WnbaGame $game, array $scheduleIndex, array $gameTeamsByPk): ?string
    {
        if ($game->wnba_stats_game_id) {
            return (string) $game->wnba_stats_game_id;
        }

        $sides = $gameTeamsByPk[(int) $game->id] ?? null;
        if ($sides === null || $sides['home'] === null || $sides['away'] === null) {
            return null;
        }

        $date = $game->game_date?->format('Y-m-d');
        if ($date === null) {
            return null;
        }

        $matches = $scheduleIndex[$this->scheduleKey($date, $sides['home'], $sides['away'])] ?? [];
        if (count($matches) !== 1) {
            return null;
        }

        $oracleId = (string) $matches[0]['game_id'];
        $this->persistGameMapping($game, $oracleId, $matches[0], $sides);

        return $oracleId;
    }

    /**
     * @param  array<string, mixed>  $scheduleRow
     * @param  array{home: string|null, away: string|null, home_team_id: string|null, away_team_id: string|null}  $sides
     */
    private function persistGameMapping(WnbaGame $game, string $oracleId, array $scheduleRow, array $sides): void
    {
        if ($this->dryRun) {
            return;
        }

        $game->wnba_stats_game_id = $oracleId;
        $game->save();

        if (! empty($scheduleRow['home_team_id']) && ! empty($sides['home_team_id'])) {
            $this->linkTeamId((string) $sides['home_team_id'], (string) $scheduleRow['home_team_id']);
        }
        if (! empty($scheduleRow['away_team_id']) && ! empty($sides['away_team_id'])) {
            $this->linkTeamId((string) $sides['away_team_id'], (string) $scheduleRow['away_team_id']);
        }
    }

    private function linkTeamId(string $ourTeamId, string $oracleTeamId): void
    {
        $team = WnbaTeam::query()->where('team_id', $ourTeamId)->first();
        if ($team === null || $team->wnba_stats_team_id) {
            return;
        }

        $collision = WnbaTeam::query()
            ->where('wnba_stats_team_id', $oracleTeamId)
            ->where('team_id', '!=', $ourTeamId)
            ->exists();
        if ($collision) {
            return;
        }

        $team->wnba_stats_team_id = $oracleTeamId;
        $team->save();
    }

    /**
     * @param  array{game_id: string|null, players: list<array<string, mixed>>, teams: list<array<string, mixed>>}  $mapped
     * @return array<string, int>
     */
    private function diffGame(WnbaGame $game, array $mapped): array
    {
        $counts = [
            'stat_mismatches' => 0,
            'players_unmapped' => 0,
            'players_missing' => 0,
            'ids_linked' => 0,
        ];

        $ourTeams = WnbaGameTeam::query()
            ->where('game_id', $game->id)
            ->with('team')
            ->get()
            ->keyBy(fn (WnbaGameTeam $row) => (string) $row->team_id);

        $teamByTricode = [];
        $teamByOracleId = [];
        foreach ($ourTeams as $row) {
            $abbr = TeamCatalog::canonicalAbbreviation((string) ($row->team?->team_abbreviation ?? ''));
            if ($abbr !== '') {
                $teamByTricode[$abbr] = $row;
            }
            if ($row->team?->wnba_stats_team_id) {
                $teamByOracleId[(string) $row->team->wnba_stats_team_id] = $row;
            }
        }

        foreach ($mapped['teams'] as $oracleTeam) {
            $ourTeam = null;
            if (! empty($oracleTeam['wnba_stats_team_id'])) {
                $ourTeam = $teamByOracleId[(string) $oracleTeam['wnba_stats_team_id']] ?? null;
            }
            $ourTeam ??= $teamByTricode[(string) ($oracleTeam['team_tricode'] ?? '')] ?? null;
            if ($ourTeam === null) {
                continue;
            }

            if (! empty($oracleTeam['wnba_stats_team_id']) && $ourTeam->team && ! $ourTeam->team->wnba_stats_team_id && ! $this->dryRun) {
                $this->linkTeamId((string) $ourTeam->team_id, (string) $oracleTeam['wnba_stats_team_id']);
            }

            $counts['stat_mismatches'] += $this->diffTeamStats($game, $ourTeam, $oracleTeam);
        }

        $ourPlayers = WnbaPlayerGame::query()
            ->where('game_id', $game->id)
            ->with('player')
            ->get();

        $matchedOurIds = [];
        $oracleByFingerprint = [];
        foreach ($mapped['players'] as $oraclePlayer) {
            $fp = $this->fingerprint($oraclePlayer);
            $oracleByFingerprint[$fp][] = $oraclePlayer;
        }

        foreach ($mapped['players'] as $oraclePlayer) {
            $match = $this->matchPlayer($ourPlayers, $oraclePlayer, $teamByTricode, $teamByOracleId, $oracleByFingerprint);
            if ($match === null) {
                if (! ($oraclePlayer['did_not_play'] ?? false)) {
                    $counts['players_unmapped']++;
                    $pid = (string) ($oraclePlayer['wnba_stats_player_id'] ?? 'unknown');
                    $this->flag(
                        'player',
                        $pid,
                        'oracle_player_unmapped',
                        sprintf(
                            'oracle player %s %s (%s) in game %s could not be matched deterministically',
                            $oraclePlayer['first_name'] ?? '',
                            $oraclePlayer['family_name'] ?? '',
                            $pid,
                            $game->game_id
                        ),
                        [['source_id' => self::SOURCE_ID, 'value' => $pid]]
                    );
                }

                continue;
            }

            [$ourRow, $linked] = $match;
            if ($linked) {
                $counts['ids_linked']++;
            }
            $matchedOurIds[(int) $ourRow->id] = true;
            $counts['stat_mismatches'] += $this->diffPlayerStats($game, $ourRow, $oraclePlayer);
        }

        foreach ($ourPlayers as $ourRow) {
            if (isset($matchedOurIds[(int) $ourRow->id])) {
                continue;
            }
            if ($ourRow->did_not_play) {
                continue;
            }
            $mins = $this->minutesParser->parseMinutes($ourRow->minutes);
            if (($mins === null || $mins <= 0) && (int) $ourRow->points === 0 && (int) $ourRow->field_goals_attempted === 0) {
                continue;
            }

            $counts['players_missing']++;
            $athleteId = (string) ($ourRow->player?->athlete_id ?? $ourRow->player_id);
            $this->flag(
                'player_game_stat',
                "{$game->game_id}|{$athleteId}",
                'missing_from_oracle',
                "our player-game row for {$athleteId} in game {$game->game_id} has no matching league box row",
                [['source_id' => 'canonical', 'value' => (string) $ourRow->id]]
            );
        }

        return $counts;
    }

    /**
     * @param  Collection<int, WnbaPlayerGame>  $ourPlayers
     * @param  array<string, mixed>  $oraclePlayer
     * @param  array<string, WnbaGameTeam>  $teamByTricode
     * @param  array<string, WnbaGameTeam>  $teamByOracleId
     * @param  array<string, list<array<string, mixed>>>  $oracleByFingerprint
     * @return array{0: WnbaPlayerGame, 1: bool}|null
     */
    private function matchPlayer(
        Collection $ourPlayers,
        array $oraclePlayer,
        array $teamByTricode,
        array $teamByOracleId,
        array $oracleByFingerprint,
    ): ?array {
        $oraclePlayerId = (string) ($oraclePlayer['wnba_stats_player_id'] ?? '');
        if ($oraclePlayerId !== '') {
            foreach ($ourPlayers as $row) {
                if ((string) ($row->player?->wnba_stats_player_id ?? '') === $oraclePlayerId) {
                    return [$row, false];
                }
            }
        }

        $ourTeam = null;
        if (! empty($oraclePlayer['wnba_stats_team_id'])) {
            $ourTeam = $teamByOracleId[(string) $oraclePlayer['wnba_stats_team_id']] ?? null;
        }
        $ourTeam ??= $teamByTricode[(string) ($oraclePlayer['team_tricode'] ?? '')] ?? null;
        if ($ourTeam === null) {
            return null;
        }

        $fp = $this->fingerprint($oraclePlayer);
        if (count($oracleByFingerprint[$fp] ?? []) !== 1) {
            return null;
        }

        $candidates = $ourPlayers->filter(function (WnbaPlayerGame $row) use ($ourTeam, $fp) {
            if ((string) $row->team_id !== (string) $ourTeam->team_id) {
                return false;
            }
            if ($row->player?->wnba_stats_player_id) {
                return false;
            }

            return $this->fingerprintFromModel($row) === $fp;
        })->values();

        if ($candidates->count() !== 1 || $oraclePlayerId === '') {
            return null;
        }

        /** @var WnbaPlayerGame $matched */
        $matched = $candidates->first();
        $linked = false;
        if (! $this->dryRun && $matched->player) {
            $collision = WnbaPlayer::query()
                ->where('wnba_stats_player_id', $oraclePlayerId)
                ->where('id', '!=', $matched->player->id)
                ->exists();
            if (! $collision) {
                $matched->player->wnba_stats_player_id = $oraclePlayerId;
                $matched->player->save();
                $linked = true;
            }
        }

        return [$matched, $linked];
    }

    /**
     * @param  array<string, mixed>  $oracleTeam
     */
    private function diffTeamStats(WnbaGame $game, WnbaGameTeam $our, array $oracleTeam): int
    {
        $mismatches = 0;
        $entityKey = "{$game->game_id}|{$our->team_id}";

        foreach (self::TEAM_STAT_FIELDS as $field) {
            $ours = $field === 'team_score'
                ? (int) $our->team_score
                : (int) ($our->{$field} ?? 0);
            $theirs = $field === 'team_score'
                ? (int) ($oracleTeam['team_score'] ?? $oracleTeam['points'] ?? 0)
                : (int) ($oracleTeam[$field] ?? 0);

            if ($ours === $theirs) {
                continue;
            }

            $mismatches++;
            $this->flag(
                'team_game_stat',
                $entityKey,
                $field,
                "league oracle {$field} {$theirs} != ours {$ours} for game {$game->game_id} team {$our->team_id}",
                [
                    ['source_id' => 'canonical', 'value' => (string) $ours],
                    ['source_id' => self::SOURCE_ID, 'value' => (string) $theirs],
                ]
            );
        }

        return $mismatches;
    }

    /**
     * @param  array<string, mixed>  $oraclePlayer
     */
    private function diffPlayerStats(WnbaGame $game, WnbaPlayerGame $our, array $oraclePlayer): int
    {
        $mismatches = 0;
        $athleteId = (string) ($our->player?->athlete_id ?? $our->player_id);
        $entityKey = "{$game->game_id}|{$athleteId}";

        foreach (self::PLAYER_STAT_FIELDS as $field) {
            $ours = (int) ($our->{$field} ?? 0);
            $theirs = (int) ($oraclePlayer[$field] ?? 0);
            if ($ours === $theirs) {
                continue;
            }

            $mismatches++;
            $this->flag(
                'player_game_stat',
                $entityKey,
                $field,
                "league oracle {$field} {$theirs} != ours {$ours} for game {$game->game_id} player {$athleteId}",
                [
                    ['source_id' => 'canonical', 'value' => (string) $ours],
                    ['source_id' => self::SOURCE_ID, 'value' => (string) $theirs],
                ]
            );
        }

        $tolerance = (float) config('wnba.agents.verification.minutes_tolerance', 1.0);
        $ourMinutes = $this->minutesParser->parseMinutes($our->minutes);
        $oracleMinutes = $this->mapper->toFloatMinutes($oraclePlayer['minutes'] ?? null);
        if ($ourMinutes !== null && $oracleMinutes !== null && abs($ourMinutes - $oracleMinutes) > $tolerance) {
            $mismatches++;
            $this->flag(
                'player_game_stat',
                $entityKey,
                'minutes',
                "league oracle minutes {$oracleMinutes} != ours {$ourMinutes} (tolerance {$tolerance}) for game {$game->game_id} player {$athleteId}",
                [
                    ['source_id' => 'canonical', 'value' => (string) $ourMinutes],
                    ['source_id' => self::SOURCE_ID, 'value' => (string) $oracleMinutes],
                ]
            );
        }

        return $mismatches;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function fingerprint(array $stats): string
    {
        return implode('|', [
            (int) ($stats['points'] ?? 0),
            (int) ($stats['rebounds'] ?? 0),
            (int) ($stats['assists'] ?? 0),
            (int) ($stats['field_goals_made'] ?? 0),
            (int) ($stats['field_goals_attempted'] ?? 0),
            (int) ($stats['three_point_field_goals_made'] ?? 0),
            (int) ($stats['free_throws_made'] ?? 0),
            (int) round($this->mapper->toFloatMinutes($stats['minutes'] ?? null) ?? 0),
        ]);
    }

    private function fingerprintFromModel(WnbaPlayerGame $row): string
    {
        return implode('|', [
            (int) $row->points,
            (int) $row->rebounds,
            (int) $row->assists,
            (int) $row->field_goals_made,
            (int) $row->field_goals_attempted,
            (int) $row->three_point_field_goals_made,
            (int) $row->free_throws_made,
            (int) round($this->minutesParser->parseMinutes($row->minutes) ?? 0),
        ]);
    }

    private function scheduleKey(string $date, string $home, string $away): string
    {
        return $date.'|'.strtoupper($home).'@'.strtoupper($away);
    }

    /**
     * @param  list<array{source_id: string, value: string}>  $candidates
     */
    private function flag(string $entityType, string $entityKey, string $field, string $reason, array $candidates = []): void
    {
        if ($this->dryRun) {
            $this->reporter?->increment('review_queue_would_add');

            return;
        }

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
            'candidates' => $candidates,
            'resolution_reason' => $reason,
            'requires_review' => true,
            'agent_run_id' => $this->reporter?->runId(),
        ]);

        $this->reporter?->increment('review_queue_added');
    }
}
