<?php

namespace App\Services\WNBA\Data\Mappers;

class ExternalNewsMapper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function mapWnbaCom(array $payload, string $label = 'WNBA'): array
    {
        $rawItems = $payload['results']['items']
            ?? $payload['items']
            ?? [];

        if (! is_array($rawItems)) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $headline = $this->stringOrNull($item['title'] ?? null);
            $url = $this->stringOrNull($item['permalink'] ?? $item['url'] ?? null);
            if ($headline === null || $url === null) {
                continue;
            }

            $items[] = [
                'id' => isset($item['id']) ? (string) $item['id'] : null,
                'headline' => $headline,
                'description' => $this->stringOrNull($item['excerpt'] ?? $item['seo']['description'] ?? null),
                'published' => $this->normalizePublished($item['date'] ?? $item['modified'] ?? null),
                'type' => $this->stringOrNull($item['type'] ?? 'article'),
                'image_url' => $this->stringOrNull($item['featuredImage'] ?? null),
                'url' => $url,
                'source' => 'wnba_com',
                'source_label' => $label,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rssItems
     * @return array<int, array<string, mixed>>
     */
    public function mapYahooRss(array $rssItems, string $label = 'Yahoo Sports'): array
    {
        return $this->mapRssItems($rssItems, 'yahoo', $label);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rssItems
     * @return array<int, array<string, mixed>>
     */
    public function mapFoxRss(
        array $rssItems,
        string $label = 'FOX Sports',
        ?string $urlMustContain = '/stories/wnba/'
    ): array {
        $filtered = [];
        foreach ($rssItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = $this->stringOrNull($item['link'] ?? null);
            if ($url === null) {
                continue;
            }

            if ($urlMustContain !== null && $urlMustContain !== '' && ! str_contains($url, $urlMustContain)) {
                continue;
            }

            $filtered[] = $item;
        }

        return $this->mapRssItems($filtered, 'fox_sports', $label);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rssItems
     * @return array<int, array<string, mixed>>
     */
    private function mapRssItems(array $rssItems, string $source, string $label): array
    {
        $items = [];
        foreach ($rssItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $headline = $this->stringOrNull($item['title'] ?? null);
            $url = $this->stringOrNull($item['link'] ?? null);
            if ($headline === null || $url === null) {
                continue;
            }

            $items[] = [
                'id' => $this->stringOrNull($item['guid'] ?? null) ?? md5($url),
                'headline' => $headline,
                'description' => $this->stringOrNull($item['description'] ?? null),
                'published' => $this->normalizePublished($item['published'] ?? null),
                'type' => 'article',
                'image_url' => $this->stringOrNull($item['image_url'] ?? null),
                'url' => $url,
                'source' => $source,
                'source_label' => $label,
            ];
        }

        return $items;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function normalizePublished(mixed $value): ?string
    {
        $raw = $this->stringOrNull($value);
        if ($raw === null) {
            return null;
        }

        $timestamp = strtotime($raw);

        return $timestamp === false ? $raw : gmdate('c', $timestamp);
    }
}
