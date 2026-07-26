<?php

namespace App\Services\WNBA\Data\Providers;

use App\Services\WNBA\Data\Mappers\ExternalNewsMapper;
use App\Services\WNBA\Data\Support\ExternalNewsFeedClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ExternalNewsProvider
{
    public function __construct(
        private ExternalNewsFeedClient $client,
        private ExternalNewsMapper $mapper,
    ) {}

    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     sources: array<int, string>,
     *     errors: array<int, string>
     * }
     */
    public function fetchLeagueNews(?int $limit = null): array
    {
        if (! config('wnba.news_feeds.enabled', true)) {
            return ['items' => [], 'sources' => [], 'errors' => []];
        }

        $items = [];
        $sources = [];
        $errors = [];

        foreach ($this->enabledSources() as $key => $config) {
            try {
                $fetched = $this->fetchSource($key, $config);
                if ($fetched === []) {
                    continue;
                }

                $sources[] = $key;
                foreach ($fetched as $item) {
                    $items[] = $item;
                }
            } catch (\Throwable $e) {
                $errors[] = "{$key}: {$e->getMessage()}";
                Log::warning('External news source failed', [
                    'source' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $items = $this->sortByPublishedDesc($items);

        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        return [
            'items' => $items,
            'sources' => $sources,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function enabledSources(): array
    {
        $sources = config('wnba.news_feeds.sources', []);
        if (! is_array($sources)) {
            return [];
        }

        $enabled = [];
        foreach ($sources as $key => $config) {
            if (! is_array($config) || empty($config['enabled']) || empty($config['url'])) {
                continue;
            }
            $enabled[(string) $key] = $config;
        }

        return $enabled;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, array<string, mixed>>
     */
    private function fetchSource(string $key, array $config): array
    {
        $url = (string) $config['url'];
        $query = is_array($config['query'] ?? null) ? $config['query'] : [];
        $label = (string) ($config['label'] ?? $key);

        return match ($key) {
            'wnba_com' => $this->mapper->mapWnbaCom(
                $this->client->fetchJson($url, $query),
                $label
            ),
            'yahoo' => $this->mapper->mapYahooRss(
                $this->client->fetchRssItems($url, $query),
                $label
            ),
            'fox_sports' => $this->mapper->mapFoxRss(
                $this->client->fetchRssItems($url, $query),
                $label,
                isset($config['url_must_contain']) ? (string) $config['url_must_contain'] : '/stories/wnba/'
            ),
            default => throw new RuntimeException("Unsupported news source: {$key}"),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function sortByPublishedDesc(array $items): array
    {
        usort($items, function (array $a, array $b): int {
            $aTs = $this->publishedTimestamp($a['published'] ?? null);
            $bTs = $this->publishedTimestamp($b['published'] ?? null);

            return $bTs <=> $aTs;
        });

        return $items;
    }

    private function publishedTimestamp(mixed $value): int
    {
        if (! is_string($value) || $value === '') {
            return 0;
        }

        $ts = strtotime($value);

        return $ts === false ? 0 : $ts;
    }
}
