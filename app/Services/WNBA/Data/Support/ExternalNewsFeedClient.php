<?php

namespace App\Services\WNBA\Data\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;

class ExternalNewsFeedClient
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function fetchJson(string $url, array $query = [], ?int $cacheTtl = null): array
    {
        $body = $this->request($url, $query, $cacheTtl, 'application/json');
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new RuntimeException("News feed returned invalid JSON: {$url}");
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function fetchRssItems(string $url, array $query = [], ?int $cacheTtl = null): array
    {
        $body = $this->request($url, $query, $cacheTtl, 'application/rss+xml, application/xml, text/xml, */*');

        try {
            $xml = new SimpleXMLElement($body);
        } catch (\Throwable $e) {
            throw new RuntimeException("News feed returned invalid RSS/XML: {$url}", 0, $e);
        }

        $items = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $items[] = $this->rssItemToArray($item);
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $url, array $query, ?int $cacheTtl, string $accept): string
    {
        $cacheTtl ??= (int) config('wnba.news_feeds.cache_ttl', 600);
        $cacheKey = 'wnba_news_feed_'.md5($url.serialize($query));

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $response = Http::withHeaders([
            'User-Agent' => (string) config('wnba.news_feeds.user_agent'),
            'Accept' => $accept,
        ])
            ->timeout((int) config('wnba.news_feeds.timeout', 15))
            ->get($url, $query);

        if (! $response->successful()) {
            Log::warning('External news feed request failed', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw new RuntimeException("News feed request failed ({$response->status()}): {$url}");
        }

        $body = $response->body();
        if ($body === '') {
            throw new RuntimeException("News feed returned empty body: {$url}");
        }

        if ($cacheTtl > 0) {
            Cache::put($cacheKey, $body, $cacheTtl);
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function rssItemToArray(SimpleXMLElement $item): array
    {
        $image = null;
        foreach ($this->mediaUrls($item) as $url) {
            if ($url !== '') {
                $image = $url;
                break;
            }
        }

        $categories = [];
        foreach ($item->category ?? [] as $category) {
            $value = trim((string) $category);
            if ($value !== '') {
                $categories[] = $value;
            }
        }

        $guid = trim((string) ($item->guid ?? ''));
        $link = trim((string) ($item->link ?? ''));

        return [
            'guid' => $guid !== '' ? $guid : null,
            'title' => $this->cleanText((string) ($item->title ?? '')),
            'link' => $link !== '' ? $link : null,
            'description' => $this->cleanText((string) ($item->description ?? '')),
            'published' => trim((string) ($item->pubDate ?? '')) ?: null,
            'categories' => $categories,
            'image_url' => $image,
        ];
    }

    private function cleanText(string $value): ?string
    {
        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text !== '' ? $text : null;
    }

    /**
     * @return array<int, string>
     */
    private function mediaUrls(SimpleXMLElement $item): array
    {
        $urls = [];

        if (isset($item->enclosure['url'])) {
            $urls[] = (string) $item->enclosure['url'];
        }

        $item->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
        $nodes = @$item->xpath('.//media:content[@url]|.//media:thumbnail[@url]') ?: [];
        foreach ($nodes as $media) {
            $urls[] = (string) ($media['url'] ?? '');
        }

        return $urls;
    }
}
