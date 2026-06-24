<?php

namespace App\Services\WNBA\Data\Providers;

use App\Contracts\WnbaStatsProvider;
use App\Services\WNBA\Data\Support\SportsDataverseFetcher;

class SportsDataverseWnbaProvider implements WnbaStatsProvider
{
    public function __construct(
        private SportsDataverseFetcher $fetcher
    ) {}

    public function name(): string
    {
        return 'sportsdataverse';
    }

    public function fetchTeams(int $season, array $options = []): array
    {
        return $this->fetcher->fetchTeamBoxscores($season, $options);
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
        return true;
    }

    public function supportsIncremental(): bool
    {
        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchPlayByPlay(int $season): array
    {
        return $this->fetcher->fetchPlayByPlay($season);
    }
}
