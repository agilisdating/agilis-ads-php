<?php

declare(strict_types=1);

namespace Agilis\Ads\Tests\Integration;

use Agilis\Ads\AgilisClient;
use Agilis\Ads\Exception\AgilisException;
use Agilis\Ads\Exception\PermissionException;
use Agilis\Ads\Upload;
use PHPUnit\Framework\TestCase;

/**
 * Runs against the real API with a Test-mode key from AGILIS_TEST_API_KEY.
 *
 * Reads, plus dry-run calls that create nothing. Skipped without a key; refuses a Live key.
 */
final class TestModeTest extends TestCase
{
    private const SAMPLE_OFFER = __DIR__ . '/../Fixtures/sample-offer.png';

    /** 1280 x 720, the minimum size of an Event image. */
    private const SAMPLE_EVENT = __DIR__ . '/../Fixtures/sample-event.png';

    /**
     * Refusals caused by the state of the test account (balance, quota, audience, billing currency,
     * Terms), not by the request. Only these turn a dry run into a skip.
     */
    private const ACCOUNT_STATE_CODES = ['E3', 'E4', 'E5', 'E6', 'E9'];

    private static ?AgilisClient $client = null;

    private function client(): AgilisClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        $key = getenv('AGILIS_TEST_API_KEY');

        if (!is_string($key) || trim($key) === '') {
            self::markTestSkipped('AGILIS_TEST_API_KEY is not set.');
        }

        $key = trim($key);

        if (!str_starts_with($key, 'pk_test_')) {
            self::fail('AGILIS_TEST_API_KEY must be a Test-mode key (pk_test_). Refusing to run.');
        }

        $baseUrl = getenv('AGILIS_BASE_URL');
        self::$client = new AgilisClient($key, baseUrl: is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : AgilisClient::DEFAULT_BASE_URL);
        self::assertSame('test', self::$client->mode);

        return self::$client;
    }

    public function testCategoriesAndInterests(): void
    {
        $categories = $this->client()->categories->list();
        self::assertIsArray($categories['categories']);
        self::assertNotEmpty($categories['categories']);

        $interests = $this->client()->interests->list();
        self::assertSame(200, $interests->statusCode);
    }

    public function testOffersAndEventsLists(): void
    {
        $offers = $this->client()->offers->list(['limit' => 5]);
        self::assertIsArray($offers['data']);
        self::assertNotNull($offers->rateLimitRemaining());

        $events = $this->client()->events->list(['limit' => 5]);
        self::assertIsArray($events['data']);
    }

    public function testEventReference(): void
    {
        self::assertSame('TEST', $this->client()->eventConfig->get()['environment']);
        self::assertIsArray($this->client()->eventCategories->list()['categories']);
        self::assertIsArray($this->client()->eventCurrencies->list()['currencies']);
    }

    public function testPriceEstimate(): void
    {
        $response = $this->client()->offers->estimatePrice([
            'start_datetime' => new \DateTimeImmutable('+1 day'),
            'end_datetime' => new \DateTimeImmutable('+3 days'),
            'location' => [51.5074, -0.1278],
            'radius' => 10,
        ]);

        self::assertSame('S1', $response->returnCode());
    }

    public function testWebhookLists(): void
    {
        try {
            $endpoints = $this->client()->webhookEndpoints->list();
            $events = $this->client()->webhookEvents->list(['limit' => 5]);
        } catch (PermissionException $e) {
            self::markTestSkipped('This key may not manage webhooks: ' . $e->getMessage());
        }

        self::assertSame(200, $endpoints->statusCode);
        self::assertIsArray($events['data']);
    }

    public function testOfferCreateDryRun(): void
    {
        $categories = $this->client()->categories->list()['categories'];
        self::assertIsArray($categories);
        self::assertIsArray($categories[0]);
        $categoryId = $categories[0]['category_id'];
        self::assertIsInt($categoryId);

        try {
            $response = $this->client()->offers->create([
                'category_id' => $categoryId,
                'title' => 'SDK dry run',
                'description' => 'A dry run from the PHP SDK tests. Nothing is created.',
                'push_notification_title' => 'SDK dry run',
                'push_notification_message' => 'A dry run from the PHP SDK tests.',
                'url' => 'https://www.agilis.dating',
                'image' => Upload::fromPath(self::SAMPLE_OFFER),
                'location' => [51.5074, -0.1278],
                'radius' => 10,
                'start_datetime' => new \DateTimeImmutable('+1 day'),
                'expiry_datetime' => new \DateTimeImmutable('+3 days'),
                'payment_method' => 'B',
            ], dryRun: true);
        } catch (AgilisException $e) {
            self::skipOnAccountState($e);
        }

        self::assertTrue($response['dry_run']);
    }

    public function testEventPublishDryRun(): void
    {
        try {
            $response = $this->client()->events->publish([
                'title' => 'SDK dry run',
                'description' => 'A dry run from the PHP SDK tests. Nothing is published.',
                'venue_name' => 'Test venue',
                'address' => '1 Test Street',
                'city' => 'London',
                'country_code' => 'GB',
                'location' => [51.5133, -0.13],
                'starts_at' => new \DateTimeImmutable('+30 days'),
                'ends_at' => new \DateTimeImmutable('+30 days 3 hours'),
                'url' => 'https://www.agilis.dating',
                'image' => Upload::fromPath(self::SAMPLE_EVENT),
            ], dryRun: true);
        } catch (AgilisException $e) {
            self::skipOnAccountState($e);
        }

        self::assertTrue($response['dry_run']);
    }

    private static function skipOnAccountState(AgilisException $e): never
    {
        if (in_array($e->returnCode, self::ACCOUNT_STATE_CODES, true)) {
            self::markTestSkipped(sprintf('The test account cannot do this right now (%s): %s', $e->returnCode, $e->getMessage()));
        }

        throw $e;
    }
}
