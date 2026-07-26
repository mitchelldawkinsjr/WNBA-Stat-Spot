<?php

namespace App\Contracts;

interface WnbaStatsProvider
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function fetchTeams(int $season, array $options = []): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function fetchSchedule(int $season, array $options = []): array;

    /**
     * @param  array<string, mixed>  $options  incremental, game_ids, force
     * @return array<int, array<string, mixed>>
     */
    public function fetchPlayerBoxscores(int $season, array $options = []): array;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, array<string, mixed>>
     */
    public function fetchTeamBoxscores(int $season, array $options = []): array;

    public function supportsPlayByPlay(): bool;

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    public function pendingBoxScoreGameIds(int $season, array $options = []): array;

    public function supportsBatchedBoxScoreImport(): bool;
}
