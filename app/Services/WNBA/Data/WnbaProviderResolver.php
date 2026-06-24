<?php

namespace App\Services\WNBA\Data;

use App\Contracts\WnbaStatsProvider;
use App\Services\WNBA\Data\Providers\EspnWnbaProvider;
use App\Services\WNBA\Data\Providers\SportsBlazeWnbaProvider;
use App\Services\WNBA\Data\Providers\SportsDataverseWnbaProvider;
use App\Services\WNBA\Data\Providers\Tank01WnbaProvider;
use InvalidArgumentException;

class WnbaProviderResolver
{
    public function resolveName(string $task): string
    {
        $routing = config('wnba.data_source.routing', []);
        $name = $routing[$task] ?? config('wnba.data_source.provider', 'sportsdataverse');

        return $this->normalizeName((string) $name);
    }

    public function resolve(string $task): WnbaStatsProvider
    {
        return $this->make($this->resolveName($task));
    }

    public function resolveForImport(bool $incremental, bool $withPbp = false): WnbaStatsProvider
    {
        if ($withPbp) {
            return $this->resolve('play_by_play');
        }

        return $this->resolve($incremental ? 'incremental' : 'bulk_import');
    }

    public function make(string $providerName): WnbaStatsProvider
    {
        return match ($this->normalizeName($providerName)) {
            'tank01' => app(Tank01WnbaProvider::class),
            'sportsblaze' => app(SportsBlazeWnbaProvider::class),
            'espn' => app(EspnWnbaProvider::class),
            'sportsdataverse' => app(SportsDataverseWnbaProvider::class),
            default => throw new InvalidArgumentException("Unknown WNBA stats provider [{$providerName}]"),
        };
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim($name));
    }
}
