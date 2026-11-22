<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Unit;

use Agilis\Ads\Tests\Support\ClientTestCase;

final class PaginationTest extends ClientTestCase
{
    public function testOnePage(): void
    {
        $this->http->queueJson(self::page([['offer_id' => 1], ['offer_id' => 2]], true, 'cur_2'));

        $page = $this->client()->offers->list(['limit' => 2]);

        self::assertSame([['offer_id' => 1], ['offer_id' => 2]], $page->items());
        self::assertTrue($page->hasMore());
        self::assertSame('cur_2', $page->nextCursor());
        self::assertCount(1, $this->http->requests);
    }

    public function testAutoPagingFollowsTheCursor(): void
    {
        $this->http->queueJson(self::page([['event_id' => 1], ['event_id' => 2]], true, 'cur_2'));
        $this->http->queueJson(self::page([['event_id' => 3]], true, 'cur_3'));
        $this->http->queueJson(self::page([['event_id' => 4]], false, null));

        $ids = [];

        foreach ($this->client()->events->listAutoPaging(['limit' => 2, 'event_status' => 'upcoming']) as $event) {
            $ids[] = $event['event_id'];
        }

        self::assertSame([1, 2, 3, 4], $ids);
        self::assertCount(3, $this->http->requests);
        self::assertSame(['limit' => '2', 'event_status' => 'upcoming', 'environment' => 'test'], self::query($this->http->requests[0]));
        self::assertSame('cur_2', self::query($this->http->requests[1])['cursor']);
        self::assertSame('cur_3', self::query($this->http->requests[2])['cursor']);
        self::assertSame('upcoming', self::query($this->http->requests[2])['event_status']);
    }

    public function testAutoPagingIsLazy(): void
    {
        $this->http->queueJson(self::page([['id' => 'a']], true, 'next'));

        $iterator = $this->client()->webhookEvents->listAutoPaging();
        self::assertCount(0, $this->http->requests);

        $iterator->current();
        self::assertCount(1, $this->http->requests);
    }

    public function testAutoPagingStopsWhenThereIsNoCursor(): void
    {
        $this->http->queueJson(self::page([['offer_id' => 1]], true, null));

        $items = iterator_to_array($this->client()->offers->listAutoPaging(), false);

        self::assertCount(1, $items);
        self::assertCount(1, $this->http->requests);
    }

    public function testWebhookEventsAutoPaging(): void
    {
        $this->http->queueJson(self::page([['id' => 'whevt_1']], true, 'c2'));
        $this->http->queueJson(self::page([['id' => 'whevt_2']], false, null));

        $ids = array_map(fn(array $e): mixed => $e['id'], iterator_to_array($this->client()->webhookEvents->listAutoPaging(['delivery_success' => false]), false));

        self::assertSame(['whevt_1', 'whevt_2'], $ids);
        self::assertSame(['delivery_success' => 'false', 'cursor' => 'c2'], self::query($this->http->requests[1]));
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private static function page(array $items, bool $hasMore, ?string $cursor): array
    {
        return [
            'data' => $items,
            'pagination' => ['limit' => 2, 'count' => count($items), 'has_more' => $hasMore, 'next_cursor' => $cursor],
        ];
    }
}
