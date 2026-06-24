<?php

namespace App\Services\WNBA\Data\Providers;

use App\Contracts\WnbaStatsProvider;
use App\Services\WNBA\Data\Support\SportsBlazeFetcher;
use App\Services\WNBA\Data\Support\SportsDataverseFetcher;

class SportsBlazeWnbaProvider implements WnbaStatsProvider
{
    public function __construct(
        private SportsBlazeFetcher $fetcher
    ) {}

    public function name(): string
    {
        return 'sportsblaze';
    }

    public function fetchTeams(int $season, array $options = []): array
    {
        return $this->fetcher->fetchTeamBoxscores($season);
    }

    public function fetchSchedule(int $season, array $options = []): array
    {
        return $this->fetcher->fetchSchedule($season);
    }

    public function fetchPlayerBoxscores(int $season, array $options = []): array
    {
        return $this->fetcher->fetchPlayerBoxscores($season, $options);
    }

    public function fetchTeamBoxscores(int $season, array $options = []): array
    {
        return $this->fetcher->fetchTeamBoxscores($season, $options);
    }

    public function fetchAvailableSeasons(): array
    {
        return $this->fetcher->fetchAvailableSeasons();
    }

    public function supportsPlayByPlay(): bool
    {
        return false;
    }

    public function supportsIncremental(): bool
    {
        return false;
    }

    public function pendingBoxScoreGameIds(int $season, array $options = []): array
    {
        return [];
    }

    public function supportsBatchedBoxScoreImport(): bool
    {
        return false;
    }
}
