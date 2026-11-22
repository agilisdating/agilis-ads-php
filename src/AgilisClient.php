<?php

declare(strict_types=1);

namespace Agilis\Ads;

use Agilis\Ads\Internal\Transport;
use Agilis\Ads\Resource\Categories;
use Agilis\Ads\Resource\EventCategories;
use Agilis\Ads\Resource\EventConfig;
use Agilis\Ads\Resource\EventCurrencies;
use Agilis\Ads\Resource\Events;
use Agilis\Ads\Resource\Interests;
use Agilis\Ads\Resource\Offers;
use Agilis\Ads\Resource\WebhookEndpoints;
use Agilis\Ads\Resource\WebhookEvents;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The client for the Agilis Advertising REST API.
 *
 * ```php
 * $agilis = new AgilisClient('pk_test_...');   // or set AGILIS_API_KEY
 * $categories = $agilis->categories->list();
 * ```
 *
 * The key decides the mode: a `pk_test_` key works in Test mode and a `pk_live_` key in Live mode.
 */
final class AgilisClient
{
    public const DEFAULT_BASE_URL = 'https://ads.agilis.dating';
    public const DEFAULT_TIMEOUT = 60.0;
    public const DEFAULT_MAX_RETRIES = 2;
    public const MODE_TEST = 'test';
    public const MODE_LIVE = 'live';

    /** `test` or `live`, from the API key. */
    public readonly string $mode;

    public readonly Categories $categories;
    public readonly Interests $interests;
    public readonly Offers $offers;
    public readonly EventCategories $eventCategories;
    public readonly EventCurrencies $eventCurrencies;
    public readonly EventConfig $eventConfig;
    public readonly Events $events;
    public readonly WebhookEndpoints $webhookEndpoints;
    public readonly WebhookEvents $webhookEvents;
    public readonly Webhooks $webhooks;

    /**
     * @param string|null                  $apiKey         your key; when null, the `AGILIS_API_KEY` environment variable
     * @param string                       $baseUrl        the API's address
     * @param float                        $timeout        seconds; applied when the SDK creates the Guzzle client itself
     * @param int                          $maxRetries     retries after the first attempt
     * @param ClientInterface|null         $httpClient     any PSR-18 client; found automatically when null
     * @param RequestFactoryInterface|null $requestFactory PSR-17; found automatically when null
     * @param StreamFactoryInterface|null  $streamFactory  PSR-17; found automatically when null
     * @param (callable(float): void)|null $sleep          waits between retries; for tests
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        float $timeout = self::DEFAULT_TIMEOUT,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?callable $sleep = null,
    ) {
        $apiKey ??= self::keyFromEnvironment();

        if ($apiKey === null || trim($apiKey) === '') {
            throw new \InvalidArgumentException('No API key: pass one to the client or set the AGILIS_API_KEY environment variable.');
        }

        $apiKey = trim($apiKey);
        $this->mode = self::modeOf($apiKey);

        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('maxRetries cannot be negative.');
        }

        $transport = new Transport(
            $apiKey,
            rtrim($baseUrl, '/'),
            $maxRetries,
            $httpClient ?? self::defaultHttpClient($timeout),
            $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            $sleep,
        );

        $this->categories = new Categories($transport, $this->mode);
        $this->interests = new Interests($transport, $this->mode);
        $this->offers = new Offers($transport, $this->mode);
        $this->eventCategories = new EventCategories($transport, $this->mode);
        $this->eventCurrencies = new EventCurrencies($transport, $this->mode);
        $this->eventConfig = new EventConfig($transport, $this->mode);
        $this->events = new Events($transport, $this->mode);
        $this->webhookEndpoints = new WebhookEndpoints($transport, $this->mode);
        $this->webhookEvents = new WebhookEvents($transport, $this->mode);
        $this->webhooks = new Webhooks();
    }

    public function isLive(): bool
    {
        return $this->mode === self::MODE_LIVE;
    }

    public function isTest(): bool
    {
        return $this->mode === self::MODE_TEST;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['mode' => $this->mode, 'apiKey' => '[redacted]'];
    }

    /**
     * The mode a key works in: `test` for `pk_test_`, `live` for `pk_live_`.
     */
    public static function modeOf(#[\SensitiveParameter] string $apiKey): string
    {
        return match (true) {
            str_starts_with($apiKey, 'pk_test_') => self::MODE_TEST,
            str_starts_with($apiKey, 'pk_live_') => self::MODE_LIVE,
            default => throw new \InvalidArgumentException('The API key must start with pk_test_ or pk_live_. Create one in the Ad Portal under Manage API Keys.'),
        };
    }

    private static function keyFromEnvironment(): ?string
    {
        $value = getenv('AGILIS_API_KEY');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $value = $_ENV['AGILIS_API_KEY'] ?? $_SERVER['AGILIS_API_KEY'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function defaultHttpClient(float $timeout): ClientInterface
    {
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client([
                'timeout' => $timeout,
                'connect_timeout' => min(10.0, $timeout),
                'http_errors' => false,
            ]);
        }

        return Psr18ClientDiscovery::find();
    }
}
