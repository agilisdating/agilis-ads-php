<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Unit;

use Agilis\Ads\Exception\SignatureVerificationException;
use Agilis\Ads\Tests\Support\ClientTestCase;
use Agilis\Ads\WebhookEvent;
use Agilis\Ads\Webhooks;
use PHPUnit\Framework\Attributes\DataProvider;

final class WebhooksTest extends ClientTestCase
{
    /**
     * @return array{secret: string, now_unix: int, tolerance_seconds: int, body: string, cases: list<array{name: string, header: string, valid: bool, error?: string}>}
     */
    private static function vectors(): array
    {
        $json = file_get_contents(__DIR__ . '/../Fixtures/webhook-signature-vectors.json');
        self::assertIsString($json);

        /** @var array{secret: string, now_unix: int, tolerance_seconds: int, body: string, cases: list<array{name: string, header: string, valid: bool, error?: string}>} */
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return iterable<string, array{0: array{name: string, header: string, valid: bool, error?: string}}>
     */
    public static function cases(): iterable
    {
        foreach (self::vectors()['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /**
     * @param array{name: string, header: string, valid: bool, error?: string} $case
     */
    #[DataProvider('cases')]
    public function testSharedVector(array $case): void
    {
        $vectors = self::vectors();
        $webhooks = new Webhooks();

        if ($case['valid']) {
            $event = $webhooks->constructEvent($vectors['body'], $case['header'], $vectors['secret'], $vectors['tolerance_seconds'], $vectors['now_unix']);
            self::assertSame('event.published', $event->type);

            return;
        }

        try {
            $webhooks->constructEvent($vectors['body'], $case['header'], $vectors['secret'], $vectors['tolerance_seconds'], $vectors['now_unix']);
            self::fail(sprintf('case %s should be refused', $case['name']));
        } catch (SignatureVerificationException $e) {
            self::assertSame($case['error'] ?? null, $e->reason);
            self::assertStringNotContainsString($vectors['secret'], (string) $e);
        }
    }

    public function testTheVectorsCoverEveryCase(): void
    {
        self::assertCount(12, self::vectors()['cases']);
    }

    public function testTheParsedEvent(): void
    {
        $vectors = self::vectors();
        $event = $this->client()->webhooks->constructEvent(
            $vectors['body'],
            $vectors['cases'][0]['header'],
            $vectors['secret'],
            now: $vectors['now_unix'],
        );

        self::assertInstanceOf(WebhookEvent::class, $event);
        self::assertSame('whevt_01JD0S6Q4M8K1Z0X7C2V9B3N5R', $event->id);
        self::assertSame(1795075200, $event->created);
        self::assertTrue($event->livemode);
        self::assertSame(1, $event->version);
        self::assertSame('event', $event->resource());
        self::assertTrue($event->isEventEvent());
        self::assertFalse($event->isOfferEvent());
        self::assertSame(3104, $event->object()['event_id']);
        self::assertSame(10.0, $event->data['amount_charged']);
        self::assertSame('api', $event->request['source'] ?? null);
        self::assertSame('webhook_event', $event['object']);
        self::assertSame($event->toArray(), json_decode($vectors['body'], true));
    }

    public function testTheDefaultToleranceIsFiveMinutes(): void
    {
        $vectors = self::vectors();
        $header = $vectors['cases'][0]['header'];
        $webhooks = new Webhooks();

        $webhooks->verifyHeader($vectors['body'], $header, $vectors['secret'], now: 1795075200 + 300);

        $this->expectException(SignatureVerificationException::class);
        $webhooks->verifyHeader($vectors['body'], $header, $vectors['secret'], now: 1795075200 + 301);
    }

    public function testAToleranceOfZeroSkipsTheTimestampCheck(): void
    {
        $vectors = self::vectors();

        (new Webhooks())->verifyHeader($vectors['body'], $vectors['cases'][0]['header'], $vectors['secret'], 0, 1);

        $this->addToAssertionCount(1);
    }

    public function testASignedBodyThatIsNotJsonIsRefused(): void
    {
        $body = 'not json';
        $header = 't=1000,v1=' . hash_hmac('sha256', '1000.' . $body, 'whsec_x');

        try {
            (new Webhooks())->constructEvent($body, $header, 'whsec_x', now: 1000);
            self::fail('expected an error');
        } catch (SignatureVerificationException $e) {
            self::assertSame('payload', $e->reason);
        }
    }

    public function testAnEmptySecretIsRefused(): void
    {
        $this->expectException(SignatureVerificationException::class);

        (new Webhooks())->constructEvent('{}', 't=1000,v1=abc', '', now: 1000);
    }

    public function testUpperCaseHexIsAccepted(): void
    {
        $body = '{"id":"whevt_1","type":"offer.started","created":1000,"livemode":false,"version":1,"data":{"object":{"offer_id":45}}}';
        $header = 't=1000,v1=' . strtoupper(hash_hmac('sha256', '1000.' . $body, 'whsec_x'));

        $event = (new Webhooks())->constructEvent($body, $header, 'whsec_x', now: 1000);

        self::assertTrue($event->isOfferEvent());
        self::assertSame(45, $event->object()['offer_id']);
        self::assertNull($event->request);
    }
}
