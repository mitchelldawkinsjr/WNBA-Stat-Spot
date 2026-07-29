<?php

namespace App\Services;

use App\Contracts\WnbaStatsProvider;
use App\Models\WnbaGame;
use App\Models\WnbaGameTeam;
use App\Models\WnbaPlay;
use App\Models\WnbaPlayer;
use App\Models\WnbaPlayerGame;
use App\Models\WnbaTeam;
use App\Services\WNBA\Agents\AgentRunReporter;
use App\Services\WNBA\Agents\BoxScoreValidator;
use App\Services\WNBA\Agents\ConflictResolver;
use App\Services\WNBA\Agents\RawPayloadStore;
use App\Services\WNBA\Data\EntityMergeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WnbaDataService
{
    /** @var int Season year used for provider fetches and local cache filenames (see WNBA_CURRENT_SEASON). */
    private int $dataSeasonYear;

    /** @var array<string, mixed> */
    private array $importOptions = ['incremental' => true, 'force' => false];

    private ?AgentRunReporter $agentReporter = null;

    private ?ConflictResolver $conflictResolver = null;

    private ?int $currentRawPayloadId = null;

    public function __construct(
        private ?WnbaStatsProvider $provider = null
    ) {
        $this->provider = $provider ?? app(WnbaStatsProvider::class);
        $this->dataSeasonYear = (int) config('wnba.seasons.current_season');
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function setImportOptions(array $options): void
    {
        $this->importOptions = array_merge($this->importOptions, $options);
    }

    public function getProviderName(): string
    {
        return $this->provider->name();
    }

    /**
     * Enable agent-run instrumentation: raw payload lineage, counters, and
     * cross-source conflict resolution during saves.
     */
    public function setAgentContext(?AgentRunReporter $reporter, ?ConflictResolver $conflictResolver = null): void
    {
        $this->agentReporter = $reporter;
        $this->conflictResolver = $conflictResolver;
    }

    /**
     * @param  array<int|string, mixed>  $records
     */
    private function storeRawPayload(string $entityType, array $records): void
    {
        if ($records === []) {
            return;
        }

        try {
            $stored = app(RawPayloadStore::class)->store(
                $this->getProviderName(),
                $entityType,
                $records,
                $this->dataSeasonYear,
            );
            $this->currentRawPayloadId = $stored['id'];
            $this->agentReporter?->increment('raw_payloads_received');
            if (! $stored['changed']) {
                $this->agentReporter?->increment('raw_payloads_unchanged');
            }
        } catch (\Throwable $e) {
            // Raw storage must never block ingestion (e.g. before migrations run).
            \Illuminate\Support\Facades\Log::warning('Raw payload storage failed', [
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
            ]);
            $this->currentRawPayloadId = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lineageFields(?string $validationStatus = null): array
    {
        return [
            'source_id' => $this->getProviderName(),
            'raw_payload_id' => $this->currentRawPayloadId,
            'ingested_at' => now(),
            'validation_status' => $validationStatus,
        ];
    }

    private function trackWrite(object $model): void
    {
        if ($this->agentReporter === null) {
            return;
        }

        if ($model->wasRecentlyCreated) {
            $this->agentReporter->increment('records_created');
        } elseif ($model->wasChanged()) {
            $this->agentReporter->increment('records_updated');
        } else {
            $this->agentReporter->increment('records_unchanged');
        }
    }

    public function estimateImportCost(): int
    {
        if ($this->provider->name() !== 'tank01') {
            return 0;
        }

        $schedule = $this->provider->fetchSchedule($this->dataSeasonYear, $this->importOptions);
        $missing = 0;
        foreach ($schedule as $game) {
            $gameId = $game['game_id'] ?? null;
            if (! $gameId) {
                continue;
            }
            $gameModel = WnbaGame::where('game_id', $gameId)->first();
            if (! $gameModel || ! WnbaPlayerGame::where('game_id', $gameModel->id)->exists()) {
                $missing++;
            }
        }

        return 1 + $missing;
    }

    public function usesBatchedProviderImport(): bool
    {
        return $this->provider->supportsBatchedBoxScoreImport();
    }

    /**
     * @return array{games_processed: int, records_saved: int}
     */
    public function importBoxScoresInBatches(?int $batchSize = null): array
    {
        $batchSize = $batchSize ?? (int) config('wnba.import.game_batch_size', 10);
        $gameIds = $this->provider->pendingBoxScoreGameIds($this->dataSeasonYear, $this->importOptions);
        $gamesProcessed = 0;
        $recordsSaved = 0;

        foreach (array_chunk($gameIds, max(1, $batchSize)) as $chunk) {
            $records = $this->provider->fetchPlayerBoxscores(
                $this->dataSeasonYear,
                array_merge($this->importOptions, ['game_ids' => $chunk]),
            );
            $this->storeRawPayload('player_box_scores', $records);
            $this->saveBoxScoreData($records);
            $gamesProcessed += count($chunk);
            $recordsSaved += count($records);
            unset($records);
            gc_collect_cycles();
        }

        return [
            'games_processed' => $gamesProcessed,
            'records_saved' => $recordsSaved,
        ];
    }

    /**
     * @return array{games_processed: int, records_saved: int}
     */
    public function importTeamBoxScoresInBatches(?int $batchSize = null): array
    {
        $batchSize = $batchSize ?? (int) config('wnba.import.game_batch_size', 10);
        $gameIds = $this->provider->pendingBoxScoreGameIds($this->dataSeasonYear, $this->importOptions);
        $gamesProcessed = 0;
        $recordsSaved = 0;

        foreach (array_chunk($gameIds, max(1, $batchSize)) as $chunk) {
            $records = $this->provider->fetchTeamBoxscores(
                $this->dataSeasonYear,
                array_merge($this->importOptions, ['game_ids' => $chunk]),
            );
            $this->storeRawPayload('team_box_scores', $records);
            $this->saveTeamData($records);
            $gamesProcessed += count($chunk);
            $recordsSaved += count($records);
            unset($records);
            gc_collect_cycles();
        }

        return [
            'games_processed' => $gamesProcessed,
            'records_saved' => $recordsSaved,
        ];
    }

    public function importEspnScheduleByTeam(int $season): int
    {
        if ($this->provider->name() !== 'espn') {
            $records = $this->provider->fetchSchedule($season, $this->importOptions);
            $this->saveTeamScheduleData($records);

            return count($records);
        }

        $espn = $this->provider;
        $client = app(\App\Services\WNBA\Data\Support\EspnApiClient::class);
        $mapper = new \App\Services\WNBA\Data\Mappers\EspnMapper($season);
        $saved = 0;

        foreach ($mapper->teamIds($client->teams()) as $teamId) {
            try {
                $schedule = $client->teamSchedule($teamId, $season);
                $records = $mapper->mapSchedule($schedule['events'] ?? []);
                $this->storeRawPayload('schedule', $records);
                $this->saveTeamScheduleData($records);
                $saved += count($records);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ESPN per-team schedule import failed', [
                    'team_id' => $teamId,
                    'error' => $e->getMessage(),
                ]);
            }

            unset($schedule, $records);
            gc_collect_cycles();
        }

        return $saved;
    }

    private function mergeService(): EntityMergeService
    {
        return app(EntityMergeService::class);
    }

    private function normalizeSeasonType(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $label = strtolower((string) $value);
        if (str_contains($label, 'pre')) {
            return 1;
        }
        if (str_contains($label, 'post') || str_contains($label, 'playoff')) {
            return 3;
        }

        return 2;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recordsFromStorage(string $path): array
    {
        $decoded = json_decode(Storage::get($path), true) ?: [];

        return isset($decoded['records']) && is_array($decoded['records']) ? $decoded['records'] : [];
    }

    public function downloadBoxScoreData(): string
    {
        $records = $this->provider->fetchPlayerBoxscores($this->dataSeasonYear, $this->importOptions);
        $this->storeRawPayload('player_box_scores', $records);
        $path = "wnba/player_box_{$this->dataSeasonYear}.json";
        Storage::put($path, json_encode(['records' => $records]));

        return $path;
    }

    public function downloadTeamData(): string
    {
        $records = $this->provider->fetchTeamBoxscores($this->dataSeasonYear, $this->importOptions);
        $this->storeRawPayload('team_box_scores', $records);
        $path = "wnba/team_box_{$this->dataSeasonYear}.json";
        Storage::put($path, json_encode(['records' => $records]));

        return $path;
    }

    public function downloadTeamScheduleData(): string
    {
        $records = $this->provider->fetchSchedule($this->dataSeasonYear, $this->importOptions);
        $this->storeRawPayload('schedule', $records);
        $path = "wnba/team_schedule_{$this->dataSeasonYear}.json";
        Storage::put($path, json_encode(['records' => $records]));

        return $path;
    }

    public function downloadPbpData(): string
    {
        if (! $this->provider instanceof \App\Services\WNBA\Data\Providers\SportsDataverseWnbaProvider) {
            throw new \Exception('Current provider does not support play-by-play. Use WNBA_DATA_PROVIDER=sportsdataverse --with-pbp.');
        }

        $records = $this->provider->fetchPlayByPlay($this->dataSeasonYear);
        $this->storeRawPayload('play_by_play', $records);
        $path = "wnba/play_by_play_{$this->dataSeasonYear}.json";
        Storage::put($path, json_encode(['records' => $records]));

        return $path;
    }

    public function parseBoxScoreData(string $path): array
    {
        return $this->recordsFromStorage($path);
    }

    public function parseTeamData(string $path): array
    {
        return $this->recordsFromStorage($path);
    }

    public function parseTeamScheduleData(string $path): array
    {
        return $this->recordsFromStorage($path);
    }

    public function parsePbpData(string $path): array
    {
        return $this->recordsFromStorage($path);
    }

    public function saveBoxScoreData(array $records): void
    {
        $merge = $this->mergeService();
        $provider = $this->getProviderName();

        foreach ($records as $record) {
            $record = $merge->normalizeBoxScoreRecord($record, $provider);

            // Skip records with missing required fields
            if (empty($record['game_id']) || empty($record['team_id']) || empty($record['athlete_id'])) {
                continue;
            }

            // Create or update game
            $game = WnbaGame::updateOrCreate(
                ['game_id' => $record['game_id']],
                [
                    'espn_game_id' => $record['espn_game_id'] ?? null,
                    'tank01_game_id' => $record['tank01_game_id'] ?? null,
                    'season' => $record['season'],
                    'season_type' => $this->normalizeSeasonType($record['season_type'] ?? null),
                    'game_date' => $record['game_date'],
                    'game_date_time' => $record['game_date_time'],
                ]
            );

            // Create or update team
            $team = WnbaTeam::updateOrCreate(
                ['team_id' => $record['team_id']],
                [
                    'espn_team_id' => $merge->looksLikeEspnTeamId((string) $record['team_id']) ? $record['team_id'] : ($record['espn_team_id'] ?? null),
                    'tank01_team_id' => $record['tank01_team_id'] ?? null,
                    'team_name' => $record['team_name'] ?? 'Unknown Team',
                    'team_location' => $record['team_location'] ?? 'Unknown',
                    'team_abbreviation' => $record['team_abbreviation'] ?? 'UNK',
                    'team_display_name' => $record['team_display_name'] ?? 'Unknown Team',
                    'team_uid' => $record['team_uid'] ?? null,
                    'team_slug' => $record['team_slug'] ?? null,
                    'team_logo' => $record['team_logo'] ?? null,
                    'team_color' => $record['team_color'] ?? null,
                    'team_alternate_color' => $record['team_alternate_color'] ?? null,
                ]
            );

            // Create or update opponent team (only if opponent_team_id exists)
            $opponentTeam = null;
            if (! empty($record['opponent_team_id'])) {
                $opponentTeam = WnbaTeam::updateOrCreate(
                    ['team_id' => $record['opponent_team_id']],
                    [
                        'team_name' => $record['opponent_team_name'] ?? 'Unknown Team',
                        'team_location' => $record['opponent_team_location'] ?? 'Unknown',
                        'team_abbreviation' => $record['opponent_team_abbreviation'] ?? 'UNK',
                        'team_display_name' => $record['opponent_team_display_name'] ?? 'Unknown Team',
                        'team_uid' => $record['opponent_team_uid'] ?? null,
                        'team_slug' => $record['opponent_team_slug'] ?? null,
                        'team_logo' => $record['opponent_team_logo'] ?? null,
                        'team_color' => $record['opponent_team_color'] ?? null,
                        'team_alternate_color' => $record['opponent_team_alternate_color'] ?? null,
                    ]
                );
            }

            // Create or update player
            $playerLookup = ['athlete_id' => $record['athlete_id']];
            $playerPayload = [
                'espn_athlete_id' => $record['espn_athlete_id'] ?? ($merge->looksLikeEspnPlayerId((string) $record['athlete_id']) ? $record['athlete_id'] : null),
                'tank01_player_id' => $record['tank01_player_id'] ?? ($merge->looksLikeTank01PlayerId((string) $record['athlete_id']) ? $record['athlete_id'] : null),
                'athlete_display_name' => $record['athlete_display_name'] ?? 'Unknown Player',
                'athlete_short_name' => $record['athlete_short_name'] ?? 'Unknown',
                'athlete_jersey' => $record['athlete_jersey'] ?? null,
                'athlete_headshot_href' => $record['athlete_headshot_href'] ?? null,
                'athlete_position_name' => $record['athlete_position_name'] ?? null,
                'athlete_position_abbreviation' => $record['athlete_position_abbreviation'] ?? null,
            ];

            $player = WnbaPlayer::updateOrCreate($playerLookup, $playerPayload);

            $statValues = [
                'field_goals_made' => $record['field_goals_made'] ?? 0,
                'field_goals_attempted' => $record['field_goals_attempted'] ?? 0,
                'three_point_field_goals_made' => $record['three_point_field_goals_made'] ?? 0,
                'three_point_field_goals_attempted' => $record['three_point_field_goals_attempted'] ?? 0,
                'free_throws_made' => $record['free_throws_made'] ?? 0,
                'free_throws_attempted' => $record['free_throws_attempted'] ?? 0,
                'offensive_rebounds' => $record['offensive_rebounds'] ?? 0,
                'defensive_rebounds' => $record['defensive_rebounds'] ?? 0,
                'rebounds' => $record['rebounds'] ?? 0,
                'assists' => $record['assists'] ?? 0,
                'steals' => $record['steals'] ?? 0,
                'blocks' => $record['blocks'] ?? 0,
                'turnovers' => $record['turnovers'] ?? 0,
                'fouls' => $record['fouls'] ?? 0,
                'plus_minus' => $record['plus_minus'] ?? 0,
                'points' => $record['points'] ?? 0,
            ];

            $validation = app(BoxScoreValidator::class)->validatePlayerRecord(
                array_merge($record, $statValues)
            );
            if ($validation['status'] === BoxScoreValidator::STATUS_INVALID) {
                // Quarantine-in-place: stored but flagged so downstream
                // aggregates and predictions exclude it.
                $this->agentReporter?->increment('records_quarantined');
                $this->agentReporter?->warn(sprintf(
                    'invalid player box row (game %s, athlete %s): %s',
                    $record['game_id'],
                    $record['athlete_id'],
                    implode('; ', $validation['failures'])
                ));
            }

            // Cross-source conflict resolution: a lower-priority source may not
            // overwrite stats already ingested from a higher-priority source.
            if ($this->conflictResolver !== null) {
                $existing = WnbaPlayerGame::where('game_id', $game->id)
                    ->where('player_id', $player->id)
                    ->first();

                if ($existing !== null) {
                    $incomingWins = $this->conflictResolver->resolveStatConflicts(
                        'player_game_stat',
                        $record['game_id'].'|'.$record['athlete_id'],
                        $existing->source_id,
                        $this->getProviderName(),
                        $existing->only(array_keys($statValues)),
                        $statValues,
                    );

                    if (! $incomingWins) {
                        $this->agentReporter?->increment('records_kept_higher_priority_source');

                        continue;
                    }
                }
            }

            // Create or update player game
            $playerGame = WnbaPlayerGame::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'player_id' => $player->id,
                ],
                array_merge($statValues, [
                    'team_id' => $team->team_id,
                    'minutes' => $record['minutes'] ?? null,
                    'starter' => $record['starter'] ?? false,
                    'ejected' => $record['ejected'] ?? false,
                    'did_not_play' => $record['did_not_play'] ?? false,
                    'reason' => $record['reason'] ?? null,
                    'active' => $record['active'] ?? true,
                ], $this->lineageFields($validation['status']))
            );
            $this->trackWrite($playerGame);
        }
    }

    public function saveTeamData(array $records): void
    {
        $merge = app(EntityMergeService::class);
        $provider = $this->getProviderName();

        foreach ($records as $record) {
            try {
                $record = $merge->normalizeTeamBoxRecord($record, $provider);

                // Skip records with missing required fields
                if (empty($record['game_id']) || empty($record['team_id']) || empty($record['opponent_team_id'])) {
                    continue;
                }

                // Skip records with invalid home_away values
                if (empty($record['home_away']) || ! in_array($record['home_away'], ['home', 'away'])) {
                    continue;
                }

                // Create or update game
                $game = WnbaGame::updateOrCreate(
                    ['game_id' => $record['game_id']],
                    [
                        'season' => $record['season'] ?? null,
                        'season_type' => $this->normalizeSeasonType($record['season_type'] ?? null),
                        'game_date' => $record['game_date'] ?? null,
                        'game_date_time' => $record['game_date_time'] ?? null,
                    ]
                );

                // Create or update team — optional metadata stays null when absent
                $team = WnbaTeam::updateOrCreate(
                    ['team_id' => $record['team_id']],
                    [
                        'team_name' => $record['team_name'] ?? 'Unknown',
                        'team_location' => $record['team_location'] ?? 'Unknown',
                        'team_abbreviation' => $record['team_abbreviation'] ?? 'UNK',
                        'team_display_name' => $record['team_display_name'] ?? 'Unknown Team',
                        'team_uid' => $record['team_uid'] ?? null,
                        'team_slug' => $record['team_slug'] ?? null,
                        'team_logo' => $record['team_logo'] ?? null,
                        'team_color' => $record['team_color'] ?? null,
                        'team_alternate_color' => $record['team_alternate_color'] ?? null,
                    ]
                );

                // Create or update opponent team
                $opponentTeam = WnbaTeam::updateOrCreate(
                    ['team_id' => $record['opponent_team_id']],
                    [
                        'team_name' => $record['opponent_team_name'] ?? 'Unknown',
                        'team_location' => $record['opponent_team_location'] ?? 'Unknown',
                        'team_abbreviation' => $record['opponent_team_abbreviation'] ?? 'UNK',
                        'team_display_name' => $record['opponent_team_display_name'] ?? 'Unknown Team',
                        'team_uid' => $record['opponent_team_uid'] ?? null,
                        'team_slug' => $record['opponent_team_slug'] ?? null,
                        'team_logo' => $record['opponent_team_logo'] ?? null,
                        'team_color' => $record['opponent_team_color'] ?? null,
                        'team_alternate_color' => $record['opponent_team_alternate_color'] ?? null,
                    ]
                );

                $statValues = [
                    'team_score' => $record['team_score'] ?? 0,
                    'opponent_team_score' => $record['opponent_team_score'] ?? 0,
                    'field_goals_made' => $record['field_goals_made'] ?? 0,
                    'field_goals_attempted' => $record['field_goals_attempted'] ?? 0,
                    'three_point_field_goals_made' => $record['three_point_field_goals_made'] ?? 0,
                    'three_point_field_goals_attempted' => $record['three_point_field_goals_attempted'] ?? 0,
                    'free_throws_made' => $record['free_throws_made'] ?? 0,
                    'free_throws_attempted' => $record['free_throws_attempted'] ?? 0,
                    'offensive_rebounds' => $record['offensive_rebounds'] ?? 0,
                    'defensive_rebounds' => $record['defensive_rebounds'] ?? 0,
                    'rebounds' => $record['rebounds'] ?? 0,
                    'assists' => $record['assists'] ?? 0,
                    'steals' => $record['steals'] ?? 0,
                    'blocks' => $record['blocks'] ?? 0,
                    'turnovers' => $record['turnovers'] ?? 0,
                    'fouls' => $record['fouls'] ?? 0,
                ];

                $validation = app(BoxScoreValidator::class)->validateTeamRecord($record);
                if ($validation['status'] === BoxScoreValidator::STATUS_INVALID) {
                    $this->agentReporter?->increment('records_quarantined');
                    $this->agentReporter?->warn(sprintf(
                        'invalid team box row (game %s, team %s): %s',
                        $record['game_id'],
                        $record['team_id'],
                        implode('; ', $validation['failures'])
                    ));
                }

                if ($this->conflictResolver !== null) {
                    $existing = WnbaGameTeam::where('game_id', $game->id)
                        ->where('team_id', $team->team_id)
                        ->first();

                    // Schedule import seeds 0-0 placeholder rows; those are not
                    // conflicting observations.
                    $isPlaceholder = $existing !== null
                        && ((int) $existing->team_score + (int) $existing->opponent_team_score) === 0;

                    if ($existing !== null && ! $isPlaceholder) {
                        $incomingWins = $this->conflictResolver->resolveStatConflicts(
                            'team_game_stat',
                            $record['game_id'].'|'.$record['team_id'],
                            $existing->source_id,
                            $this->getProviderName(),
                            $existing->only(array_keys($statValues)),
                            $statValues,
                        );

                        if (! $incomingWins) {
                            $this->agentReporter?->increment('records_kept_higher_priority_source');

                            continue;
                        }
                    }
                }

                // Create or update game team
                $gameTeam = WnbaGameTeam::updateOrCreate(
                    [
                        'game_id' => $game->id,
                        'team_id' => $team->team_id,
                    ],
                    array_merge($statValues, [
                        'opponent_team_id' => $opponentTeam->team_id,
                        'home_away' => $record['home_away'],
                        'team_winner' => $record['team_winner'] ?? false,
                    ], $this->lineageFields($validation['status']))
                );
                $this->trackWrite($gameTeam);
            } catch (\Throwable $e) {
                $this->agentReporter?->increment('records_skipped');
                $this->agentReporter?->warn(sprintf(
                    'skipped team box row (game %s, team %s): %s',
                    $record['game_id'] ?? 'unknown',
                    $record['team_id'] ?? 'unknown',
                    $e->getMessage()
                ));
                Log::warning('saveTeamData skipped row', [
                    'game_id' => $record['game_id'] ?? null,
                    'team_id' => $record['team_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function saveTeamScheduleData(array $records): void
    {
        $merge = $this->mergeService();
        $provider = $this->getProviderName();

        foreach ($records as $record) {
            try {
                $record = $merge->normalizeScheduleRecord($record, $provider);

                if (empty($record['game_id'])) {
                    continue;
                }

                // Create or update game — optional venue/status metadata stays null
                $game = WnbaGame::updateOrCreate(
                    ['game_id' => $record['game_id']],
                    [
                        'espn_game_id' => $record['espn_game_id'] ?? null,
                        'tank01_game_id' => $record['tank01_game_id'] ?? null,
                        'season' => $record['season'] ?? null,
                        'season_type' => $this->normalizeSeasonType($record['season_type'] ?? null),
                        'game_date' => $record['game_date'] ?? null,
                        'game_date_time' => $record['game_date_time'] ?? null,
                        'venue_id' => $record['venue_id'] ?? null,
                        'venue_name' => $record['venue_name'] ?? null,
                        'venue_city' => $record['venue_city'] ?? null,
                        'venue_state' => $record['venue_state'] ?? null,
                        'venue_country' => $record['venue_country'] ?? null,
                        'venue_capacity' => $record['venue_capacity'] ?? null,
                        'venue_surface' => $record['venue_surface'] ?? null,
                        'venue_indoor' => $record['venue_indoor'] ?? null,
                        'status_id' => $record['status_id'] ?? null,
                        'status_name' => $record['status_name'] ?? null,
                        'status_type' => $record['status_type'] ?? null,
                        'status_abbreviation' => $record['status_abbreviation'] ?? null,
                        'source_id' => $this->getProviderName(),
                        'raw_payload_id' => $this->currentRawPayloadId,
                        'ingested_at' => now(),
                    ]
                );
                $this->trackWrite($game);

                $homeTeam = null;
                $awayTeam = null;

                // Create or update home team (only if home_team_id is not null)
                if (! empty($record['home_team_id'])) {
                    $homeTeam = WnbaTeam::updateOrCreate(
                        ['team_id' => $record['home_team_id']],
                        [
                            'team_name' => $record['home_team_name'] ?? 'Unknown',
                            'team_location' => $record['home_team_location'] ?? 'Unknown',
                            'team_abbreviation' => $record['home_team_abbreviation'] ?? 'UNK',
                            'team_display_name' => $record['home_team_display_name'] ?? 'Unknown Team',
                            'team_uid' => $record['home_team_uid'] ?? null,
                            'team_slug' => $record['home_team_slug'] ?? null,
                            'team_logo' => $record['home_team_logo'] ?? null,
                            'team_color' => $record['home_team_color'] ?? null,
                            'team_alternate_color' => $record['home_team_alternate_color'] ?? null,
                        ]
                    );
                }

                // Create or update away team (only if away_team_id is not null)
                if (! empty($record['away_team_id'])) {
                    $awayTeam = WnbaTeam::updateOrCreate(
                        ['team_id' => $record['away_team_id']],
                        [
                            'team_name' => $record['away_team_name'] ?? 'Unknown',
                            'team_location' => $record['away_team_location'] ?? 'Unknown',
                            'team_abbreviation' => $record['away_team_abbreviation'] ?? 'UNK',
                            'team_display_name' => $record['away_team_display_name'] ?? 'Unknown Team',
                            'team_uid' => $record['away_team_uid'] ?? null,
                            'team_slug' => $record['away_team_slug'] ?? null,
                            'team_logo' => $record['away_team_logo'] ?? null,
                            'team_color' => $record['away_team_color'] ?? null,
                            'team_alternate_color' => $record['away_team_alternate_color'] ?? null,
                        ]
                    );
                }

                // Create placeholder WnbaGameTeam rows so upcoming games appear in
                // schedules. firstOrCreate (not updateOrCreate) so a schedule sync
                // can never reset real box-score values back to zero.
                if ($homeTeam && $awayTeam) {
                    $homeRow = WnbaGameTeam::firstOrCreate(
                        [
                            'game_id' => $game->id,
                            'team_id' => $homeTeam->team_id,
                        ],
                        array_merge($this->placeholderGameTeamStats(), [
                            'opponent_team_id' => $awayTeam->team_id,
                            'home_away' => 'home',
                        ], $this->lineageFields())
                    );

                    $awayRow = WnbaGameTeam::firstOrCreate(
                        [
                            'game_id' => $game->id,
                            'team_id' => $awayTeam->team_id,
                        ],
                        array_merge($this->placeholderGameTeamStats(), [
                            'opponent_team_id' => $homeTeam->team_id,
                            'home_away' => 'away',
                        ], $this->lineageFields())
                    );

                    // Scoreboard points (live sync) — advance scores without
                    // requiring a full box / teamStats payload.
                    $this->applyScheduleScores(
                        $homeRow,
                        $awayRow,
                        isset($record['home_team_score']) ? (int) $record['home_team_score'] : null,
                        isset($record['away_team_score']) ? (int) $record['away_team_score'] : null,
                    );
                }
            } catch (\Throwable $e) {
                $this->agentReporter?->increment('records_skipped');
                $this->agentReporter?->warn(sprintf(
                    'skipped schedule row (game %s): %s',
                    $record['game_id'] ?? 'unknown',
                    $e->getMessage()
                ));
                Log::warning('saveTeamScheduleData skipped row', [
                    'game_id' => $record['game_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Advance live/final scores from scoreboard without wiping box stats.
     * Scores are monotonic during a game — only move forward.
     */
    private function applyScheduleScores(
        WnbaGameTeam $homeRow,
        WnbaGameTeam $awayRow,
        ?int $homeScore,
        ?int $awayScore,
    ): void {
        if ($homeScore === null && $awayScore === null) {
            return;
        }

        $homeChanged = false;
        $awayChanged = false;

        if ($homeScore !== null && $homeScore >= (int) $homeRow->team_score) {
            $homeRow->team_score = $homeScore;
            $homeRow->opponent_team_score = $awayScore ?? (int) $homeRow->opponent_team_score;
            $homeChanged = true;
        }

        if ($awayScore !== null && $awayScore >= (int) $awayRow->team_score) {
            $awayRow->team_score = $awayScore;
            $awayRow->opponent_team_score = $homeScore ?? (int) $awayRow->opponent_team_score;
            $awayChanged = true;
        }

        if ($homeChanged || $awayChanged) {
            $homeWinner = (int) $homeRow->team_score > (int) $awayRow->team_score;
            $homeRow->team_winner = $homeWinner;
            $awayRow->team_winner = ! $homeWinner && (int) $awayRow->team_score !== (int) $homeRow->team_score;
            if ($homeChanged) {
                $homeRow->save();
            }
            if ($awayChanged) {
                $awayRow->save();
            }
        }
    }

    /**
     * @return array<string, int|bool>
     */
    private function placeholderGameTeamStats(): array
    {
        return [
            'team_winner' => false,
            'team_score' => 0,
            'opponent_team_score' => 0,
            'field_goals_made' => 0,
            'field_goals_attempted' => 0,
            'three_point_field_goals_made' => 0,
            'three_point_field_goals_attempted' => 0,
            'free_throws_made' => 0,
            'free_throws_attempted' => 0,
            'offensive_rebounds' => 0,
            'defensive_rebounds' => 0,
            'rebounds' => 0,
            'assists' => 0,
            'steals' => 0,
            'blocks' => 0,
            'turnovers' => 0,
            'fouls' => 0,
        ];
    }

    public function savePbpData(array $records): void
    {
        foreach ($records as $record) {
            if (empty($record['game_id']) || empty($record['play_id'])) {
                continue;
            }

            // PBP rows carry no team metadata or player names, so ensure rows
            // exist without overwriting details ingested from richer sources.
            $game = WnbaGame::firstOrCreate(
                ['game_id' => $record['game_id']],
                [
                    'season' => $record['season'] ?? $this->dataSeasonYear,
                    'season_type' => $this->normalizeSeasonType($record['season_type'] ?? null),
                    'game_date' => $record['game_date'] ?? null,
                    'game_date_time' => $record['game_date_time'] ?? null,
                ]
            );

            $teamId = (string) ($record['team_id'] ?? '');
            if ($teamId !== '') {
                WnbaTeam::firstOrCreate(
                    ['team_id' => $teamId],
                    [
                        'team_name' => $record['team_name'] ?? 'Unknown Team',
                        'team_location' => $record['team_location'] ?? 'Unknown',
                        'team_abbreviation' => $record['team_abbreviation'] ?? 'UNK',
                        'team_display_name' => $record['team_display_name'] ?? 'Unknown Team',
                    ]
                );
            }

            $player = null;
            if (! empty($record['athlete_id'])) {
                $player = WnbaPlayer::firstOrCreate(
                    ['athlete_id' => $record['athlete_id']],
                    [
                        'athlete_display_name' => $record['athlete_display_name'] ?? 'Unknown Player',
                        'athlete_short_name' => $record['athlete_short_name'] ?? 'Unknown',
                    ]
                );
            }

            $play = WnbaPlay::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'play_id' => $record['play_id'],
                ],
                array_merge([
                    'play_sequence_number' => (int) ($record['play_sequence_number'] ?? 0),
                    'period' => (string) ($record['period'] ?? ''),
                    'period_display_value' => (string) ($record['period_display_value'] ?? ''),
                    'clock_display_value' => (string) ($record['clock_display_value'] ?? ''),
                    'team_id' => $teamId !== '' ? $teamId : null,
                    'player_id' => $player?->id,
                    'play_type_id' => (string) ($record['play_type_id'] ?? ''),
                    'play_type_text' => (string) ($record['play_type_text'] ?? ''),
                    'play_type_abbreviation' => (string) ($record['play_type_abbreviation'] ?? ''),
                    'play_text' => (string) ($record['play_text'] ?? ''),
                    'score_value' => (int) ($record['score_value'] ?? 0),
                    'score_team_id' => $record['score_team_id'] ?? null,
                ], $this->lineageFields())
            );
            $this->trackWrite($play);
        }
    }
}
