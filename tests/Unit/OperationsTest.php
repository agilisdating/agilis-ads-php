<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Unit;

use Agilis\Ads\AgilisClient;
use Agilis\Ads\Tests\Support\ClientTestCase;
use Agilis\Ads\Upload;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every operation's method, path and query, with no body where none is expected.
 */
final class OperationsTest extends ClientTestCase
{
    /**
     * @return iterable<string, array{0: \Closure(AgilisClient): mixed, 1: string, 2: string, 3: array<string, string>}>
     */
    public static function simpleOperations(): iterable
    {
        yield 'categories.list' => [fn(AgilisClient $c) => $c->categories->list(), 'GET', '/categories', []];
        yield 'interests.list' => [fn(AgilisClient $c) => $c->interests->list(), 'GET', '/interests', []];
        yield 'offers.list' => [fn(AgilisClient $c) => $c->offers->list(['limit' => 5, 'offer_status' => 'active']), 'GET', '/offers', ['limit' => '5', 'offer_status' => 'active', 'environment' => 'test']];
        yield 'offers.pause' => [fn(AgilisClient $c) => $c->offers->pause(45), 'POST', '/offers/45/pause', []];
        yield 'offers.resume' => [fn(AgilisClient $c) => $c->offers->resume(45), 'POST', '/offers/45/resume', []];
        yield 'offers.analytics' => [fn(AgilisClient $c) => $c->offers->analytics(45, ['interval' => '7d']), 'GET', '/offers/45/analytics', ['interval' => '7d']];
        yield 'offers.delete' => [fn(AgilisClient $c) => $c->offers->delete(45), 'DELETE', '/offers/45', []];
        yield 'offers.delete dryRun' => [fn(AgilisClient $c) => $c->offers->delete(45, dryRun: true), 'DELETE', '/offers/45', ['dry_run' => 'true']];
        yield 'eventCategories.list' => [fn(AgilisClient $c) => $c->eventCategories->list(), 'GET', '/event-categories', []];
        yield 'eventCurrencies.list' => [fn(AgilisClient $c) => $c->eventCurrencies->list(), 'GET', '/event-currencies', []];
        yield 'eventConfig.get' => [fn(AgilisClient $c) => $c->eventConfig->get(), 'GET', '/event-config', []];
        yield 'events.list' => [fn(AgilisClient $c) => $c->events->list(['event_status' => 'upcoming', 'owner_user_id' => 41]), 'GET', '/events', ['event_status' => 'upcoming', 'owner_user_id' => '41', 'environment' => 'test']];
        yield 'events.get' => [fn(AgilisClient $c) => $c->events->get(3104), 'GET', '/events/3104', []];
        yield 'events.cancel' => [fn(AgilisClient $c) => $c->events->cancel(3104), 'POST', '/events/3104/cancel', []];
        yield 'events.cancel dryRun' => [fn(AgilisClient $c) => $c->events->cancel(3104, dryRun: true), 'POST', '/events/3104/cancel', ['dry_run' => 'true']];
        yield 'events.delete' => [fn(AgilisClient $c) => $c->events->delete(3104), 'DELETE', '/events/3104', []];
        yield 'events.delete dryRun' => [fn(AgilisClient $c) => $c->events->delete(3104, dryRun: true), 'DELETE', '/events/3104', ['dry_run' => 'true']];
        yield 'events.analytics' => [fn(AgilisClient $c) => $c->events->analytics(3104, ['interval' => 'all']), 'GET', '/events/3104/analytics', ['interval' => 'all']];
        yield 'webhookEndpoints.list' => [fn(AgilisClient $c) => $c->webhookEndpoints->list(), 'GET', '/webhook-endpoints', []];
        yield 'webhookEndpoints.get' => [fn(AgilisClient $c) => $c->webhookEndpoints->get('we_01ABC'), 'GET', '/webhook-endpoints/we_01ABC', []];
        yield 'webhookEndpoints.delete' => [fn(AgilisClient $c) => $c->webhookEndpoints->delete('we_01ABC'), 'DELETE', '/webhook-endpoints/we_01ABC', []];
        yield 'webhookEvents.list' => [fn(AgilisClient $c) => $c->webhookEvents->list(['type' => 'offer.started', 'delivery_success' => false, 'limit' => 20]), 'GET', '/webhook-events', ['type' => 'offer.started', 'delivery_success' => 'false', 'limit' => '20']];
        yield 'webhookEvents.get' => [fn(AgilisClient $c) => $c->webhookEvents->get('whevt_01XYZ'), 'GET', '/webhook-events/whevt_01XYZ', []];
    }

    /**
     * @param \Closure(AgilisClient): mixed $call
     * @param array<string, string>         $query
     */
    #[DataProvider('simpleOperations')]
    public function testOperation(\Closure $call, string $method, string $path, array $query): void
    {
        $call($this->client());

        $request = $this->http->last();
        self::assertSame($method, $request->getMethod());
        self::assertSame('ads.agilis.dating', $request->getUri()->getHost());
        self::assertSame('https', $request->getUri()->getScheme());
        self::assertSame($path, $request->getUri()->getPath());
        self::assertEquals($query, self::query($request));
        self::assertSame('', (string) $request->getBody());
        self::assertFalse($request->hasHeader('Idempotency-Key'));
        self::assertSame(self::TEST_KEY, $request->getHeaderLine('x-api-key'));
        self::assertCount(1, $this->http->requests);
    }

    public function testListUsesTheCallersEnvironment(): void
    {
        $this->client()->offers->list(['environment' => 'TEST']);

        self::assertSame(['environment' => 'TEST'], self::query($this->http->last()));
    }

    public function testListsUseTheLiveModeOfALiveKey(): void
    {
        $client = $this->client(self::LIVE_KEY);
        $client->offers->list();
        $client->events->list();

        self::assertSame('/offers', $this->http->requests[0]->getUri()->getPath());
        self::assertSame('environment=live', $this->http->requests[0]->getUri()->getQuery());
        self::assertSame('/events', $this->http->requests[1]->getUri()->getPath());
        self::assertSame('environment=live', $this->http->requests[1]->getUri()->getQuery());
    }

    public function testEstimatePriceSendsJson(): void
    {
        $this->client()->offers->estimatePrice([
            'start_datetime' => new \DateTimeImmutable('2027-07-10 09:00:00', new \DateTimeZone('Europe/London')),
            'end_datetime' => '2027-07-12T20:30:00Z',
            'location' => [51.5074, -0.1278],
            'radius' => 10,
            'gender' => ['Male', 'Female'],
            'interests' => [3, 7],
        ]);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/offers/price-estimate', $request->getUri()->getPath());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'start_datetime' => '2027-07-10T08:00:00Z',
            'end_datetime' => '2027-07-12T20:30:00Z',
            'location' => [51.5074, -0.1278],
            'radius' => 10,
            'gender' => ['Male', 'Female'],
            'interests' => [3, 7],
            'environment' => 'TEST',
        ], self::jsonBody($request));
        self::assertFalse($request->hasHeader('Idempotency-Key'));
    }

    public function testCreateOfferSendsMultipartWithTheImage(): void
    {
        $this->client()->offers->create([
            'category_id' => 1,
            'title' => '20% off summer sale',
            'description' => 'Discount valid through July',
            'push_notification_title' => '20% off this summer',
            'push_notification_message' => 'Show this at the till.',
            'button_preset' => 'book_now',
            'url' => 'https://example.com/offer',
            'image' => Upload::fromBytes('PNGDATA', 'offer.png'),
            'location' => [51.5074, -0.1278],
            'radius' => 10,
            'gender' => ['Male', 'Female'],
            'interests' => [3, 7],
            'start_datetime' => '2027-07-10T08:00:00Z',
            'expiry_datetime' => '2027-07-12T20:30:00Z',
            'payment_method' => 'B',
        ], idempotencyKey: 'my-key-1');

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/offers', $request->getUri()->getPath());
        self::assertSame('', $request->getUri()->getQuery());
        self::assertSame('my-key-1', $request->getHeaderLine('Idempotency-Key'));

        self::assertSame([
            'category_id' => ['1'],
            'title' => ['20% off summer sale'],
            'description' => ['Discount valid through July'],
            'push_notification_title' => ['20% off this summer'],
            'push_notification_message' => ['Show this at the till.'],
            'button_preset' => ['book_now'],
            'url' => ['https://example.com/offer'],
            'location[]' => ['51.5074', '-0.1278'],
            'radius' => ['10'],
            'gender[]' => ['Male', 'Female'],
            'interests[]' => ['3', '7'],
            'start_datetime' => ['2027-07-10T08:00:00Z'],
            'expiry_datetime' => ['2027-07-12T20:30:00Z'],
            'payment_method' => ['B'],
            'environment' => ['TEST'],
        ], self::fields($request));

        $files = array_values(array_filter(self::parts($request), fn(array $p): bool => $p['filename'] !== null));
        self::assertCount(1, $files);
        self::assertSame(['name' => 'image', 'filename' => 'offer.png', 'type' => 'image/png', 'value' => 'PNGDATA'], $files[0]);
    }

    public function testCreateOfferGeneratesAUuidIdempotencyKey(): void
    {
        $client = $this->client();
        $client->offers->create(self::offerParams());
        $first = $this->http->last()->getHeaderLine('Idempotency-Key');
        $client->offers->create(self::offerParams());
        $second = $this->http->last()->getHeaderLine('Idempotency-Key');

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
        self::assertNotSame($first, $second, 'each call gets its own key');
    }

    public function testCreateOfferDryRun(): void
    {
        $this->client()->offers->create(self::offerParams(), dryRun: true);

        $request = $this->http->last();
        self::assertSame(['dry_run' => 'true'], self::query($request));
        self::assertTrue($request->hasHeader('Idempotency-Key'));
    }

    public function testPreviewEventSendsMultipartWithCrop(): void
    {
        $this->client()->events->preview([
            'title' => 'Beginners Cuban Salsa',
            'image' => Upload::fromBytes('JPEGDATA', 'salsa.JPG'),
            'crop' => ['x' => 0, 'y' => 120, 'w' => 1920],
        ]);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/events/preview', $request->getUri()->getPath());
        self::assertFalse($request->hasHeader('Idempotency-Key'));
        self::assertSame([
            'title' => ['Beginners Cuban Salsa'],
            'crop[x]' => ['0'],
            'crop[y]' => ['120'],
            'crop[w]' => ['1920'],
            'environment' => ['TEST'],
        ], self::fields($request));
        $parts = self::parts($request);
        self::assertSame('image', $parts[1]['name']);
        self::assertSame('salsa.JPG', $parts[1]['filename']);
        self::assertSame('image/jpeg', $parts[1]['type']);
    }

    public function testPublishEvent(): void
    {
        $this->client(self::LIVE_KEY)->events->publish(self::eventParams(), idempotencyKey: 'evt-key', dryRun: true);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/events', $request->getUri()->getPath());
        self::assertSame(['dry_run' => 'true'], self::query($request));
        self::assertSame('evt-key', $request->getHeaderLine('Idempotency-Key'));
        $fields = self::fields($request);
        self::assertSame(['51.5133', '-0.13'], $fields['location[]']);
        self::assertSame(['12'], $fields['price_min']);
        self::assertSame(['12.5'], $fields['price_max']);
        self::assertSame(['B'], $fields['payment_method']);
        self::assertSame(['LIVE'], $fields['environment']);
        self::assertSame(['2027-10-16T18:30:00Z'], $fields['starts_at']);
    }

    public function testEditEventWithoutAFileIsAMultipartPostWithTheOverrideHeader(): void
    {
        $this->client()->events->edit(3104, [
            'title' => 'Thursday Comedy Club',
            'location' => [51.5, -0.12],
            'crop' => ['x' => 10, 'y' => 20, 'w' => 1280],
        ]);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/events/3104', $request->getUri()->getPath());
        self::assertSame('PATCH', $request->getHeaderLine('X-HTTP-Method-Override'));
        self::assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'title' => ['Thursday Comedy Club'],
            'location[]' => ['51.5', '-0.12'],
            'crop[x]' => ['10'],
            'crop[y]' => ['20'],
            'crop[w]' => ['1280'],
        ], self::fields($request));
        self::assertSame([], array_filter(self::parts($request), fn(array $p): bool => $p['filename'] !== null), 'no file part');
    }

    public function testEditEventWithAFileIsAPostWithTheOverrideHeader(): void
    {
        $this->client()->events->edit(3104, [
            'title' => 'Thursday Comedy Club',
            'image' => Upload::fromBytes('JPEGDATA', 'new.jpeg'),
        ]);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/events/3104', $request->getUri()->getPath());
        self::assertSame('PATCH', $request->getHeaderLine('X-HTTP-Method-Override'));
        self::assertSame(['title' => ['Thursday Comedy Club']], self::fields($request));
        self::assertSame('new.jpeg', self::parts($request)[1]['filename']);
    }

    public function testRescheduleEvent(): void
    {
        $this->client()->events->reschedule(3104, ['starts_at' => '2027-10-23T18:30:00Z', 'ends_at' => '2027-10-23T23:00:00Z']);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/events/3104/reschedule', $request->getUri()->getPath());
        self::assertSame(['starts_at' => '2027-10-23T18:30:00Z', 'ends_at' => '2027-10-23T23:00:00Z'], self::jsonBody($request));
    }

    public function testCreateWebhookEndpoint(): void
    {
        $this->client()->webhookEndpoints->create([
            'url' => 'https://example.com/hooks/agilis',
            'enabled_events' => ['offer.started', 'event.published'],
            'description' => 'Main',
        ]);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/webhook-endpoints', $request->getUri()->getPath());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'url' => 'https://example.com/hooks/agilis',
            'enabled_events' => ['offer.started', 'event.published'],
            'description' => 'Main',
        ], self::jsonBody($request));
    }

    public function testUpdateWebhookEndpoint(): void
    {
        $this->client()->webhookEndpoints->update('we_01ABC', ['status' => 'disabled']);

        $request = $this->http->last();
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame('/webhook-endpoints/we_01ABC', $request->getUri()->getPath());
        self::assertSame(['status' => 'disabled'], self::jsonBody($request));
    }

    public function testRollWebhookSecret(): void
    {
        $this->client()->webhookEndpoints->rollSecret('we_01ABC', ['expires_in' => '24h']);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/webhook-endpoints/we_01ABC/roll-secret', $request->getUri()->getPath());
        self::assertSame(['expires_in' => '24h'], self::jsonBody($request));
    }

    public function testSendTestWebhookWithNoParametersSendsAnEmptyObject(): void
    {
        $this->client()->webhookEndpoints->sendTest('we_01ABC');

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/webhook-endpoints/we_01ABC/test', $request->getUri()->getPath());
        self::assertSame('{}', (string) $request->getBody());
    }

    public function testResendWebhookEvent(): void
    {
        $this->client()->webhookEvents->resend('whevt_01XYZ', ['endpoint_id' => 'we_01ABC']);

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/webhook-events/whevt_01XYZ/resend', $request->getUri()->getPath());
        self::assertSame(['endpoint_id' => 'we_01ABC'], self::jsonBody($request));
    }

    public function testPathParametersAreEncoded(): void
    {
        $this->client()->webhookEndpoints->get('we/../x y');

        self::assertSame('/webhook-endpoints/we%2F..%2Fx%20y', $this->http->last()->getUri()->getPath());
    }

    /**
     * @return array{category_id: int, title: string, description: string, push_notification_title: string, push_notification_message: string, url: string, image: Upload, location: array{0: float, 1: float}, radius: int, start_datetime: string, expiry_datetime: string, payment_method: 'B'}
     */
    private static function offerParams(): array
    {
        return [
            'category_id' => 1,
            'title' => 'Offer',
            'description' => 'Description',
            'push_notification_title' => 'Title here',
            'push_notification_message' => 'Message here',
            'url' => 'https://example.com',
            'image' => Upload::fromBytes('PNG', 'a.png'),
            'location' => [51.5, -0.1],
            'radius' => 10,
            'start_datetime' => '2027-07-10T08:00:00Z',
            'expiry_datetime' => '2027-07-12T20:30:00Z',
            'payment_method' => 'B',
        ];
    }

    /**
     * @return array{title: string, description: string, venue_name: string, address: string, city: string, country_code: string, location: array{0: float, 1: float}, starts_at: \DateTimeImmutable, ends_at: string, url: string, price_min: int, price_max: float, currency: string, image: Upload}
     */
    private static function eventParams(): array
    {
        return [
            'title' => 'Beginners Cuban Salsa',
            'description' => 'Drop-in class.',
            'venue_name' => 'Bar Salsa',
            'address' => '96 Charing Cross Road',
            'city' => 'London',
            'country_code' => 'GB',
            'location' => [51.5133, -0.13],
            'starts_at' => new \DateTimeImmutable('2027-10-16T18:30:00Z'),
            'ends_at' => '2027-10-16T23:00:00Z',
            'url' => 'https://example.com/classes',
            'price_min' => 12,
            'price_max' => 12.5,
            'currency' => 'GBP',
            'image' => Upload::fromBytes('JPEG', 'salsa.jpg'),
        ];
    }
}
