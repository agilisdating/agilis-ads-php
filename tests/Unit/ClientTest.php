<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Unit;

use Agilis\Ads\AgilisClient;
use Agilis\Ads\Tests\Support\ClientTestCase;
use Agilis\Ads\Version;
use GuzzleHttp\Psr7\HttpFactory;

final class ClientTest extends ClientTestCase
{
    protected function tearDown(): void
    {
        putenv('AGILIS_API_KEY');
        unset($_ENV['AGILIS_API_KEY'], $_SERVER['AGILIS_API_KEY']);
    }

    public function testTheModeComesFromTheKey(): void
    {
        $test = $this->client(self::TEST_KEY);
        self::assertSame('test', $test->mode);
        self::assertTrue($test->isTest());
        self::assertFalse($test->isLive());

        $live = $this->client(self::LIVE_KEY);
        self::assertSame('live', $live->mode);
        self::assertTrue($live->isLive());
        self::assertSame('live', AgilisClient::modeOf('pk_live_abc'));
    }

    public function testTheKeyIsReadFromTheEnvironment(): void
    {
        putenv('AGILIS_API_KEY=' . self::LIVE_KEY);
        $factory = new HttpFactory();

        $client = new AgilisClient(httpClient: $this->http, requestFactory: $factory, streamFactory: $factory);
        $client->categories->list();

        self::assertSame('live', $client->mode);
        self::assertSame(self::LIVE_KEY, $this->http->last()->getHeaderLine('x-api-key'));
    }

    public function testAMissingKeyIsRefused(): void
    {
        putenv('AGILIS_API_KEY');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AGILIS_API_KEY');

        new AgilisClient(httpClient: $this->http);
    }

    public function testAKeyWithAnUnknownPrefixIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pk_test_ or pk_live_');

        $this->client('abc123');
    }

    public function testTheStandardHeaders(): void
    {
        $this->client()->categories->list();

        $request = $this->http->last();
        self::assertSame(self::TEST_KEY, $request->getHeaderLine('x-api-key'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame(
            sprintf('agilis-ads-php/%s (PHP %s)', Version::VERSION, PHP_VERSION),
            $request->getHeaderLine('User-Agent'),
        );
        self::assertFalse($request->hasHeader('Authorization'), 'the key is never sent as a Bearer token');
    }

    public function testTheBaseUrlCanBeChanged(): void
    {
        $factory = new HttpFactory();
        $client = new AgilisClient(
            self::TEST_KEY,
            baseUrl: 'http://localhost:8080/',
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
        );

        $client->interests->list();

        self::assertSame('http://localhost:8080/interests', (string) $this->http->last()->getUri());
    }

    public function testTheDefaultClientIsCreatedWithoutAnHttpClient(): void
    {
        $client = new AgilisClient(self::TEST_KEY, timeout: 5);

        self::assertSame('test', $client->mode);
    }

    public function testVersion(): void
    {
        self::assertSame('0.1.0', Version::VERSION);
    }

    public function testNegativeRetriesAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->client(maxRetries: -1);
    }
}
