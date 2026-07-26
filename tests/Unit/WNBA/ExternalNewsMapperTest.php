<?php

namespace Tests\Unit\WNBA;

use App\Services\WNBA\Data\Mappers\ExternalNewsMapper;
use App\Services\WNBA\Data\Support\NewsItemAggregator;
use Tests\TestCase;

class ExternalNewsMapperTest extends TestCase
{
    public function test_maps_wnba_com_content_api_payload(): void
    {
        $mapper = new ExternalNewsMapper;
        $items = $mapper->mapWnbaCom([
            'results' => [
                'items' => [
                    [
                        'id' => 123,
                        'title' => 'WNBA All-Star Recap',
                        'permalink' => 'https://www.wnba.com/news/all-star-recap',
                        'excerpt' => 'Game storylines.',
                        'date' => '2026-07-26T03:41:28Z',
                        'featuredImage' => 'https://cdn.wnba.com/story.png',
                        'type' => 'post',
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('123', $items[0]['id']);
        $this->assertSame('WNBA All-Star Recap', $items[0]['headline']);
        $this->assertSame('wnba_com', $items[0]['source']);
        $this->assertSame('WNBA', $items[0]['source_label']);
        $this->assertSame('https://www.wnba.com/news/all-star-recap', $items[0]['url']);
    }

    public function test_fox_rss_keeps_only_wnba_story_urls(): void
    {
        $mapper = new ExternalNewsMapper;
        $items = $mapper->mapFoxRss([
            [
                'guid' => '1',
                'title' => 'WNBA Title Odds',
                'link' => 'https://www.foxsports.com/stories/wnba/2026-wnba-title-odds',
                'description' => 'Odds board.',
                'published' => 'Sun, 26 Jul 2026 12:00:00 +0000',
            ],
            [
                'guid' => '2',
                'title' => 'Indy 500 side story',
                'link' => 'https://www.foxsports.com/stories/motor/caitlin-clark-indy-500',
                'description' => 'Cross sport.',
                'published' => 'Sun, 26 Jul 2026 11:00:00 +0000',
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('fox_sports', $items[0]['source']);
        $this->assertStringContainsString('/stories/wnba/', $items[0]['url']);
    }

    public function test_aggregator_dedupes_by_url_and_headline(): void
    {
        $aggregator = new NewsItemAggregator;
        $merged = $aggregator->merge(
            [
                [
                    'headline' => 'Clark Returns Friday',
                    'url' => 'https://www.wnba.com/news/clark-returns?utm=1',
                    'published' => '2026-07-26T12:00:00Z',
                    'source' => 'wnba_com',
                ],
            ],
            [
                [
                    'headline' => 'Clark Returns Friday!',
                    'url' => 'https://sports.yahoo.com/articles/other',
                    'published' => '2026-07-26T11:00:00Z',
                    'source' => 'yahoo',
                ],
                [
                    'headline' => 'Different Story',
                    'url' => 'https://www.foxsports.com/stories/wnba/other',
                    'published' => '2026-07-26T10:00:00Z',
                    'source' => 'fox_sports',
                ],
            ],
        );

        $this->assertCount(2, $merged);
        $this->assertSame('wnba_com', $merged[0]['source']);
        $this->assertSame('fox_sports', $merged[1]['source']);
    }
}
