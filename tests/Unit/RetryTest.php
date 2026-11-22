<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Unit;

use Agilis\Ads\Exception\ApiException;
use Agilis\Ads\Exception\ConnectionException;
use Agilis\Ads\Exception\RateLimitException;
use Agilis\Ads\Internal\Transport;
use Agilis\Ads\Tests\Support\ClientTestCase;
use Agilis\Ads\Tests\Support\MockHttpClient;
use Agilis\Ads\Upload;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RetryTest extends ClientTestCase
{
    public function testTheIdempotencyKeyIsKeptAcrossRetries(): void
    {
        $this->http->queue(
            MockHttpClient::json(['message' => 'busy'], 503),
            self::connectionError(),
            MockHttpClient::json(['return_code' => 'S1', 'offer_id' => 45]),
        );

        $response = $this->client()->offers->create(self::offer());

        self::assertSame(45, $response['offer_id']);
        self::assertCount(3, $this->http->requests);
        $keys = array_map(fn(RequestInterface $r): string => $r->getHeaderLine('Idempotency-Key'), $this->http->requests);
        self::assertNotSame('', $keys[0]);
        self::assertSame([$keys[0], $keys[0], $keys[0]], $keys);
        $bodies = array_map(fn(RequestInterface $r): string => (string) $r->getBody(), $this->http->requests);
        self::assertSame([$bodies[0], $bodies[0], $bodies[0]], $bodies, 'the same body is resent');
    }

    public function testThePublishIdempotencyKeyIsKeptAcrossRetries(): void
    {
        $this->http->queue(MockHttpClient::json([], 500), MockHttpClient::json(['return_code' => 'S1']));

        $this->client()->events->publish([
            'title' => 't', 'description' => 'd', 'venue_name' => 'v', 'address' => 'a', 'city' => 'c',
            'country_code' => 'GB', 'location' => [1, 2], 'starts_at' => '2027-01-01T00:00:00Z',
            'ends_at' => '2027-01-01T01:00:00Z', 'url' => 'https://example.com',
        ], idempotencyKey: 'caller-key');

        self::assertCount(2, $this->http->requests);
        self::assertSame('caller-key', $this->http->requests[0]->getHeaderLine('Idempotency-Key'));
        self::assertSame('caller-key', $this->http->requests[1]->getHeaderLine('Idempotency-Key'));
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function retryableStatuses(): iterable
    {
        foreach ([429, 500, 502, 503, 504] as $status) {
            yield (string) $status => [$status];
        }
    }

    #[DataProvider('retryableStatuses')]
    public function testGetIsRetried(int $status): void
    {
        $this->http->queue(MockHttpClient::json([], $status), MockHttpClient::json(['categories' => []]));

        $this->client()->categories->list();

        self::assertCount(2, $this->http->requests);
    }

    #[DataProvider('retryableStatuses')]
    public function testDeleteIsRetried(int $status): void
    {
        $this->http->queue(MockHttpClient::json([], $status), MockHttpClient::json(['return_code' => 'S1']));

        $this->client()->offers->delete(45);

        self::assertCount(2, $this->http->requests);
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function serverErrors(): iterable
    {
        foreach ([500, 502, 503, 504] as $status) {
            yield (string) $status => [$status];
        }
    }

    #[DataProvider('serverErrors')]
    public function testPostWithoutAnIdempotencyKeyIsNotRetriedOnA5xx(int $status): void
    {
        $this->http->queue(MockHttpClient::json(['message' => 'oops'], $status));

        try {
            $this->client()->offers->pause(45);
            self::fail('expected an error');
        } catch (ApiException $e) {
            self::assertSame($status, $e->httpStatus);
        }

        self::assertCount(1, $this->http->requests);
        self::assertSame([], $this->sleeps);
    }

    #[DataProvider('serverErrors')]
    public function testPatchIsNotRetriedOnA5xx(int $status): void
    {
        $this->http->queue(MockHttpClient::json([], $status));

        try {
            $this->client()->webhookEndpoints->update('we_1', ['description' => 'x']);
            self::fail('expected an error');
        } catch (ApiException) {
        }

        $this->http->queue(MockHttpClient::json([], $status));

        try {
            $this->client()->events->edit(1, ['title' => 'x']);
            self::fail('expected an error');
        } catch (ApiException) {
        }

        self::assertCount(2, $this->http->requests);
    }

    public function testPostWithoutAnIdempotencyKeyIsRetriedOn429(): void
    {
        $this->http->queue(MockHttpClient::json([], 429, ['Retry-After' => '3']), MockHttpClient::json(['return_code' => 'S1']));

        $this->client()->offers->pause(45);

        self::assertCount(2, $this->http->requests);
        self::assertSame([3.0], $this->sleeps);
    }

    public function testConnectionErrorsAreRetriedForEveryMethod(): void
    {
        $this->http->queue(self::connectionError(), MockHttpClient::json(['return_code' => 'S1']));

        $this->client()->webhookEndpoints->update('we_1', ['description' => 'x']);

        self::assertCount(2, $this->http->requests);
    }

    public function testNotRetriedOnA4xx(): void
    {
        foreach ([400, 401, 403, 404, 409, 422] as $status) {
            $this->http->queue(MockHttpClient::json([], $status));

            try {
                $this->client()->categories->list();
                self::fail('expected an error');
            } catch (\Agilis\Ads\Exception\AgilisException) {
            }
        }

        self::assertCount(6, $this->http->requests);
        self::assertSame([], $this->sleeps);
    }

    public function testRetriesStopAtTheMaximum(): void
    {
        $this->http->queue(
            MockHttpClient::json([], 503),
            MockHttpClient::json([], 503),
            MockHttpClient::json(['message' => 'still down'], 503),
            MockHttpClient::json(['categories' => []]),
        );

        try {
            $this->client()->categories->list();
            self::fail('expected an error');
        } catch (ApiException $e) {
            self::assertSame('still down', $e->getMessage());
        }

        self::assertCount(3, $this->http->requests, 'the first attempt and 2 retries');
        self::assertCount(2, $this->sleeps);
    }

    public function testMaxRetriesZeroSendsOnce(): void
    {
        $this->http->queue(MockHttpClient::json([], 503));

        $this->expectException(ApiException::class);

        try {
            $this->client(maxRetries: 0)->categories->list();
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testRetryAfterIsHonoured(): void
    {
        $this->http->queue(
            MockHttpClient::json([], 429, ['Retry-After' => '7']),
            MockHttpClient::json([], 503, ['Retry-After' => '1.5']),
            MockHttpClient::json(['categories' => []]),
        );

        $this->client()->categories->list();

        self::assertSame([7.0, 1.5], $this->sleeps);
    }

    public function testRateLimitErrorCarriesRetryAfter(): void
    {
        $this->http->queue(
            MockHttpClient::json([], 429, ['Retry-After' => '2']),
            MockHttpClient::json([], 429, ['Retry-After' => '2']),
            MockHttpClient::json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '42']),
        );

        try {
            $this->client()->categories->list();
            self::fail('expected an error');
        } catch (RateLimitException $e) {
            self::assertSame(42.0, $e->retryAfter);
            self::assertSame(429, $e->httpStatus);
            self::assertSame('42', $e->header('retry-after'));
        }
    }

    public function testTheExponentialWaitsWithoutRetryAfter(): void
    {
        $this->http->queue(MockHttpClient::json([], 503), MockHttpClient::json([], 503), MockHttpClient::json([]));

        $this->client()->categories->list();

        self::assertCount(2, $this->sleeps);
        self::assertGreaterThanOrEqual(0.375, $this->sleeps[0]);
        self::assertLessThanOrEqual(0.625, $this->sleeps[0]);
        self::assertGreaterThanOrEqual(0.75, $this->sleeps[1]);
        self::assertLessThanOrEqual(1.25, $this->sleeps[1]);
    }

    public function testBackoffFormula(): void
    {
        $factory = new HttpFactory();
        $low = new Transport('pk_test_x', 'https://example.test', 2, new MockHttpClient(), $factory, $factory, null, fn(): float => 0.0);
        $mid = new Transport('pk_test_x', 'https://example.test', 2, new MockHttpClient(), $factory, $factory, null, fn(): float => 0.5);
        $high = new Transport('pk_test_x', 'https://example.test', 2, new MockHttpClient(), $factory, $factory, null, fn(): float => 1.0);

        self::assertSame(0.5, $mid->backoff(0));
        self::assertSame(1.0, $mid->backoff(1));
        self::assertSame(2.0, $mid->backoff(2));
        self::assertSame(4.0, $mid->backoff(3));
        self::assertSame(8.0, $mid->backoff(4));
        self::assertSame(8.0, $mid->backoff(9));
        self::assertSame(0.375, $low->backoff(0));
        self::assertSame(0.625, $high->backoff(0));
        self::assertSame(6.0, $low->backoff(4));
        self::assertSame(8.0, $high->backoff(4), 'never more than 8 seconds');
    }

    public function testConnectionErrorAfterEveryRetry(): void
    {
        $this->http->queue(self::connectionError(), self::connectionError(), self::connectionError());

        try {
            $this->client()->categories->list();
            self::fail('expected an error');
        } catch (ConnectionException $e) {
            self::assertNull($e->httpStatus);
            self::assertStringContainsString('timed out', $e->getMessage());
            self::assertStringNotContainsString(self::TEST_KEY, (string) $e);
        }

        self::assertCount(3, $this->http->requests);
    }

    /**
     * @return \Closure(RequestInterface): ResponseInterface
     */
    private static function connectionError(): \Closure
    {
        return static function (RequestInterface $request): ResponseInterface {
            throw new ConnectException('Connection timed out', $request);
        };
    }

    /**
     * @return array{category_id: int, title: string, description: string, push_notification_title: string, push_notification_message: string, url: string, image: Upload, location: array{0: float, 1: float}, radius: int, start_datetime: string, expiry_datetime: string, payment_method: 'B'}
     */
    private static function offer(): array
    {
        return [
            'category_id' => 1, 'title' => 'T', 'description' => 'D', 'push_notification_title' => 'Hello',
            'push_notification_message' => 'Hello there', 'url' => 'https://example.com',
            'image' => Upload::fromBytes('PNG', 'a.png'), 'location' => [51.5, -0.1], 'radius' => 10,
            'start_datetime' => '2027-07-10T08:00:00Z', 'expiry_datetime' => '2027-07-12T20:30:00Z', 'payment_method' => 'B',
        ];
    }
}
