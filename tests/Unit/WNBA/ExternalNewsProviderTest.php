<?php

namespace Tests\Unit\WNBA;

use App\Services\WNBA\Data\Providers\ExternalNewsProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalNewsProviderTest extends TestCase
{
    public function test_fetches_and_maps_configured_wnba_feeds(): void
    {
        config([
            'wnba.news_feeds.enabled' => true,
            'wnba.news_feeds.cache_ttl' => 0,
            'wnba.news_feeds.sources' => [
                'wnba_com' => [
                    'enabled' => true,
                    'label' => 'WNBA',
                    'url' => 'https://content-api-prod.nba.com/public/1/leagues/wnba/content',
                    'query' => ['types' => 'article', 'count' => 25],
                ],
                'yahoo' => [
                    'enabled' => true,
                    'label' => 'Yahoo Sports',
                    'url' => 'https://sports.yahoo.com/wnba/rss/',
                ],
                'fox_sports' => [
                    'enabled' => true,
                    'label' => 'FOX Sports',
                    'url' => 'https://api.foxsports.com/v2/content/optimized-rss',
                    'query' => [
                        'partnerKey' => 'test-key',
                        'size' => 30,
                        'tags' => 'fs/wnba',
                    ],
                    'url_must_contain' => '/stories/wnba/',
                ],
            ],
        ]);

        Http::fake([
            'content-api-prod.nba.com/*' => Http::response([
                'results' => [
                    'items' => [
                        [
                            'id' => 1,
                            'title' => 'Official WNBA Story',
                            'permalink' => 'https://www.wnba.com/news/official',
                            'excerpt' => 'From the league.',
                            'date' => '2026-07-26T15:00:00Z',
                        ],
                    ],
                ],
            ]),
            'sports.yahoo.com/*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
<item>
  <guid>y1</guid>
  <title>Yahoo WNBA Headline</title>
  <link>https://sports.yahoo.com/articles/yahoo-wnba</link>
  <description>Yahoo blurb</description>
  <pubDate>Sun, 26 Jul 2026 14:00:00 +0000</pubDate>
</item>
</channel></rss>
XML),
            'api.foxsports.com/*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
<item>
  <guid>f1</guid>
  <title>FOX WNBA Headline</title>
  <link>https://www.foxsports.com/stories/wnba/fox-story</link>
  <description>Fox blurb</description>
  <category>wnba</category>
  <pubDate>Sun, 26 Jul 2026 13:00:00 +0000</pubDate>
</item>
<item>
  <guid>f2</guid>
  <title>Non WNBA path</title>
  <link>https://www.foxsports.com/stories/motor/side-story</link>
  <description>Skip me</description>
  <category>motor</category>
  <pubDate>Sun, 26 Jul 2026 16:00:00 +0000</pubDate>
</item>
</channel></rss>
XML),
        ]);

        $result = app(ExternalNewsProvider::class)->fetchLeagueNews(10);

        $this->assertSame(['wnba_com', 'yahoo', 'fox_sports'], $result['sources']);
        $this->assertCount(3, $result['items']);
        $this->assertSame('Official WNBA Story', $result['items'][0]['headline']);
        $this->assertSame('Yahoo WNBA Headline', $result['items'][1]['headline']);
        $this->assertSame('FOX WNBA Headline', $result['items'][2]['headline']);
        $this->assertSame([], $result['errors']);
    }
}
