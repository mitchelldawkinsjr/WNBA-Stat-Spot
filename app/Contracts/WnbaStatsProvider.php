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

    /**
     * @return array<int, array{year: int, season: string}>
     */
    public function fetchAvailableSeasons(): array;

    public function supportsPlayByPlay(): bool;

    public function supportsIncremental(): bool;
}
