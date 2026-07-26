<?php

namespace App\Services\WNBA\Data\Support;

class NewsItemAggregator
{
    /**
     * Merge multi-source news items, dedupe by URL then headline, newest first.
     *
     * @param  array<int, array<string, mixed>>  ...$groups
     * @return array<int, array<string, mixed>>
     */
    public function merge(array ...$groups): array
    {
        $merged = [];
        foreach ($groups as $group) {
            foreach ($group as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $merged[] = $item;
            }
        }

        $deduped = [];
        $seenUrls = [];
        $seenHeadlines = [];

        foreach ($this->sortByPublishedDesc($merged) as $item) {
            $urlKey = $this->normalizeUrl($item['url'] ?? null);
            $headlineKey = $this->normalizeHeadline($item['headline'] ?? null);

            if ($urlKey !== null && isset($seenUrls[$urlKey])) {
                continue;
            }
            if ($headlineKey !== null && isset($seenHeadlines[$headlineKey])) {
                continue;
            }

            if ($urlKey !== null) {
                $seenUrls[$urlKey] = true;
            }
            if ($headlineKey !== null) {
                $seenHeadlines[$headlineKey] = true;
            }

            $deduped[] = $item;
        }

        return $deduped;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function stampSource(array $items, string $source, string $label): array
    {
        return array_map(function (array $item) use ($source, $label): array {
            $item['source'] = $item['source'] ?? $source;
            $item['source_label'] = $item['source_label'] ?? $label;

            return $item;
        }, $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function sortByPublishedDesc(array $items): array
    {
        usort($items, function (array $a, array $b): int {
            return $this->publishedTimestamp($b['published'] ?? null)
                <=> $this->publishedTimestamp($a['published'] ?? null);
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

    private function normalizeUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $parts = parse_url(strtolower(trim($url)));
        if ($parts === false || empty($parts['host'])) {
            return strtolower(rtrim(trim($url), '/'));
        }

        $path = rtrim($parts['path'] ?? '', '/');

        return ($parts['host'] ?? '').$path;
    }

    private function normalizeHeadline(mixed $headline): ?string
    {
        if (! is_string($headline) || $headline === '') {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($headline));

        return $normalized !== null && $normalized !== '' ? $normalized : null;
    }
}
