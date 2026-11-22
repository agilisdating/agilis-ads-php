<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Unit;

use Agilis\Ads\Exception\AgilisException;
use Agilis\Ads\Exception\ApiException;
use Agilis\Ads\Exception\AuthenticationException;
use Agilis\Ads\Exception\ConflictException;
use Agilis\Ads\Exception\InvalidRequestException;
use Agilis\Ads\Exception\NotFoundException;
use Agilis\Ads\Exception\PermissionException;
use Agilis\Ads\Exception\RateLimitException;
use Agilis\Ads\Tests\Support\ClientTestCase;
use Agilis\Ads\Tests\Support\MockHttpClient;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;

final class ErrorsTest extends ClientTestCase
{
    /**
     * @return iterable<string, array{0: int, 1: class-string<AgilisException>}>
     */
    public static function statuses(): iterable
    {
        yield '400' => [400, InvalidRequestException::class];
        yield '401' => [401, AuthenticationException::class];
        yield '403' => [403, PermissionException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, InvalidRequestException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ApiException::class];
        yield '503' => [503, ApiException::class];
        yield '418' => [418, ApiException::class];
        yield '302' => [302, ApiException::class];
    }

    /**
     * @param class-string<AgilisException> $class
     */
    #[DataProvider('statuses')]
    public function testStatusMapping(int $status, string $class): void
    {
        $this->http->queue(MockHttpClient::json(
            ['return_code' => 'E1', 'message' => 'Refused for a reason.'],
            $status,
            ['X-RateLimit-Remaining' => '17'],
        ));

        try {
            $this->client(maxRetries: 0)->categories->list();
            self::fail('expected an error');
        } catch (AgilisException $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($status, $e->httpStatus);
            self::assertSame('E1', $e->returnCode);
            self::assertSame('Refused for a reason.', $e->getMessage());
            self::assertSame(['return_code' => 'E1', 'message' => 'Refused for a reason.'], $e->body);
            self::assertSame('17', $e->header('X-RateLimit-Remaining'));
        }
    }

    public function testAnECodeInA200IsAnInvalidRequest(): void
    {
        $this->http->queueJson(['return_code' => 'E3', 'message' => 'Your balance is too low for this Offer.']);

        try {
            $this->client()->offers->pause(45);
            self::fail('expected an error');
        } catch (InvalidRequestException $e) {
            self::assertSame(200, $e->httpStatus);
            self::assertSame('E3', $e->returnCode);
            self::assertSame('Your balance is too low for this Offer.', $e->getMessage());
        }

        self::assertCount(1, $this->http->requests, 'a refusal is not retried');
    }

    public function testS1AndS2AreSuccess(): void
    {
        $this->http->queueJson(['return_code' => 'S1', 'message' => 'Offer paused.']);
        $this->http->queueJson(['return_code' => 'S2', 'message' => 'Offer resumed.'], 200, ['X-RateLimit-Remaining' => '58', 'X-RateLimit-Limit' => '60']);
        $client = $this->client();

        self::assertSame('S1', $client->offers->pause(45)->returnCode());
        $resumed = $client->offers->resume(45);
        self::assertSame('S2', $resumed->returnCode());
        self::assertSame(200, $resumed->statusCode);
        self::assertSame(58, $resumed->rateLimitRemaining());
        self::assertSame(60, $resumed->rateLimitLimit());
        self::assertSame('58', $resumed->header('x-ratelimit-remaining'));
    }

    public function testUnknownFieldsAreKept(): void
    {
        $this->http->queueJson(['categories' => [['category_id' => 1, 'category_name' => 'Retail', 'new_field' => ['a' => 1]]], 'added_later' => true]);

        $response = $this->client()->categories->list();

        self::assertTrue($response['added_later']);
        self::assertSame([['category_id' => 1, 'category_name' => 'Retail', 'new_field' => ['a' => 1]]], $response->toArray()['categories']);
        self::assertSame(json_encode($response), json_encode($response->data));
    }

    public function testAMessageWithoutABodyStillExplainsTheStatus(): void
    {
        $this->http->queue(new Response(502, [], '<html>Bad gateway</html>'));

        try {
            $this->client(maxRetries: 0)->categories->list();
            self::fail('expected an error');
        } catch (ApiException $e) {
            self::assertSame('The Agilis API returned HTTP 502.', $e->getMessage());
            self::assertSame('<html>Bad gateway</html>', $e->body);
        }
    }

    public function testTheKeyNeverAppearsInErrorText(): void
    {
        $this->http->queueJson(['message' => 'Key ' . self::TEST_KEY . ' is not valid'], 401);

        try {
            $this->client()->categories->list();
            self::fail('expected an error');
        } catch (AuthenticationException $e) {
            self::assertStringNotContainsString(self::TEST_KEY, $e->getMessage());
            self::assertStringNotContainsString(self::TEST_KEY, (string) $e);
            self::assertStringContainsString('[redacted]', $e->getMessage());
        }
    }

    public function testTheKeyIsNotInDebugOutputOrTheUrl(): void
    {
        $client = $this->client();
        $client->categories->list();

        self::assertStringNotContainsString(self::TEST_KEY, print_r($client, true));
        self::assertStringNotContainsString(self::TEST_KEY, var_export($client->__debugInfo(), true));
        self::assertStringNotContainsString(self::TEST_KEY, (string) $this->http->last()->getUri());
    }

    public function testAnInvalidKeyPrefixNeverEchoesTheKey(): void
    {
        try {
            $this->client('sk_secret_value_123');
            self::fail('expected an error');
        } catch (\InvalidArgumentException $e) {
            self::assertStringNotContainsString('sk_secret_value_123', (string) $e);
        }
    }
}
