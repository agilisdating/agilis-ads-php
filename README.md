# Agilis Advertising API PHP SDK

The official PHP client for the [Agilis Advertising REST API](https://www.agilis.dating/api-doc): create and manage Offers and Events, read their results, and verify the webhooks Agilis sends you.

- PHP 8.2 or newer
- Any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client; [Guzzle](https://docs.guzzlephp.org/) is suggested
- MIT licence

## Install

```bash
composer require agilisdating/agilis-ads guzzlehttp/guzzle
```

The SDK finds the installed PSR-18 client and PSR-17 factories with [php-http/discovery](https://docs.php-http.org/en/latest/discovery.html). Composer asks you to allow the `php-http/discovery` plugin the first time; allow it, or install a client yourself as above.

## Quick start (Test mode)

Create a Test key in the [Ad Portal](https://adportal.agilis.dating) under **Manage API Keys**, with the portal in Test mode. Keep it in an environment variable, never in code.

```bash
export AGILIS_API_KEY=pk_test_...
```

```php
<?php

require 'vendor/autoload.php';

use Agilis\Ads\AgilisClient;

$agilis = new AgilisClient(); // reads AGILIS_API_KEY

echo $agilis->mode, "\n"; // "test"

$categories = $agilis->categories->list();

foreach ($categories['categories'] as $category) {
    echo $category['category_id'], ' ', $category['category_name'], "\n";
}

$estimate = $agilis->offers->estimatePrice([
    'start_datetime' => '2027-07-10T08:00:00Z',
    'end_datetime' => '2027-07-12T20:30:00Z',
    'location' => [51.5074, -0.1278],
    'radius' => 10,
]);

echo $estimate['estimated_cost'], ' ', $estimate['currency'], "\n";
```

A Test key never shows anything to members and never charges anything. The same code works with a Live key (`pk_live_`): then changes are real.

## The client

```php
$agilis = new AgilisClient(
    apiKey: 'pk_test_...',               // default: the AGILIS_API_KEY environment variable
    baseUrl: 'https://ads.agilis.dating', // default
    timeout: 60.0,                        // seconds, default 60
    maxRetries: 2,                        // default 2
    httpClient: $myPsr18Client,           // default: Guzzle if installed, otherwise discovered
);
```

| Option | Default | Notes |
|---|---|---|
| `apiKey` | `AGILIS_API_KEY` | Must start with `pk_test_` or `pk_live_`. |
| `baseUrl` | `https://ads.agilis.dating` | |
| `timeout` | `60.0` | Applied when the SDK creates the Guzzle client itself. If you pass your own `httpClient`, set its timeout there. |
| `maxRetries` | `2` | Retries after the first attempt. `0` turns retries off. |
| `httpClient` | discovered | Any PSR-18 client. |
| `requestFactory`, `streamFactory` | discovered | PSR-17 factories. |

**Mode.** The key decides it: `pk_test_` is Test and `pk_live_` is Live. `$agilis->mode` is `test` or `live`, and `isTest()` / `isLive()` say the same, so you can refuse to run against Live:

```php
if ($agilis->isLive()) {
    throw new RuntimeException('This script only runs in Test mode.');
}
```

Operations that take `environment` fill it in from the key when you leave it out.

**Headers.** Every request sends `x-api-key`, `Accept: application/json` and `User-Agent: agilis-ads-php/<version> (PHP <version>)`. The key is never logged, never part of an error message and never put in a URL.

## Resources and methods

Path parameters are arguments; everything else is one array of parameters, with the API's own field names. Dates can be ISO 8601 strings in UTC or `DateTimeInterface` objects, which are sent in UTC.

| Resource | Method | HTTP |
|---|---|---|
| `categories` | `list()` | `GET /categories` |
| `interests` | `list()` | `GET /interests` |
| `offers` | `estimatePrice($params)` | `POST /offers/price-estimate` |
| | `create($params, $idempotencyKey = null, $dryRun = false)` | `POST /offers` (multipart) |
| | `list($params = [])` | `GET /offers` |
| | `listAutoPaging($params = [])` | every page of `GET /offers` |
| | `pause($offerId)` | `POST /offers/{offer_id}/pause` |
| | `resume($offerId)` | `POST /offers/{offer_id}/resume` |
| | `analytics($offerId, ['interval' => '7d'])` | `GET /offers/{offer_id}/analytics` |
| | `delete($offerId, $dryRun = false)` | `DELETE /offers/{offer_id}` |
| `eventCategories` | `list()` | `GET /event-categories` |
| `eventCurrencies` | `list()` | `GET /event-currencies` |
| `eventConfig` | `get()` | `GET /event-config` |
| `events` | `preview($params)` | `POST /events/preview` (multipart) |
| | `publish($params, $idempotencyKey = null, $dryRun = false)` | `POST /events` (multipart) |
| | `list($params = [])` | `GET /events` |
| | `listAutoPaging($params = [])` | every page of `GET /events` |
| | `get($eventId)` | `GET /events/{event_id}` |
| | `edit($eventId, $params)` | `PATCH /events/{event_id}` |
| | `reschedule($eventId, $params)` | `POST /events/{event_id}/reschedule` |
| | `cancel($eventId, $dryRun = false)` | `POST /events/{event_id}/cancel` |
| | `delete($eventId, $dryRun = false)` | `DELETE /events/{event_id}` |
| | `analytics($eventId, ['interval' => '30d'])` | `GET /events/{event_id}/analytics` |
| `webhookEndpoints` | `list()` | `GET /webhook-endpoints` |
| | `create($params)` | `POST /webhook-endpoints` |
| | `get($endpointId)` | `GET /webhook-endpoints/{endpoint_id}` |
| | `update($endpointId, $params)` | `PATCH /webhook-endpoints/{endpoint_id}` |
| | `delete($endpointId)` | `DELETE /webhook-endpoints/{endpoint_id}` |
| | `rollSecret($endpointId, $params = [])` | `POST /webhook-endpoints/{endpoint_id}/roll-secret` |
| | `sendTest($endpointId, $params = [])` | `POST /webhook-endpoints/{endpoint_id}/test` |
| `webhookEvents` | `list($params = [])` | `GET /webhook-events` |
| | `listAutoPaging($params = [])` | every page of `GET /webhook-events` |
| | `get($webhookEventId)` | `GET /webhook-events/{webhook_event_id}` |
| | `resend($webhookEventId, $params = [])` | `POST /webhook-events/{webhook_event_id}/resend` |
| `webhooks` | `constructEvent($payload, $header, $secret, $tolerance = 300)` | verifies a received webhook; no request |

The parameter arrays are described with PHPStan / Psalm array shapes, so static analysis and IDEs check field names and types. See the [API reference](https://www.agilis.dating/api-doc) for what each field means.

### Responses

Every method returns an `Agilis\Ads\ApiResponse`: the parsed JSON, with every field kept, so a new API field never breaks your code.

```php
$offer = $agilis->offers->pause(45);

$offer['return_code'];          // "S1" (resume answers "S2"; both are success)
$offer->get('message');
$offer->toArray();              // the whole body
$offer->statusCode;             // 200
$offer->header('X-RateLimit-Remaining');
$offer->rateLimitRemaining();   // as an int
```

### Creating an Offer

The image can be a file path, an `Upload`, a stream resource or a PSR-7 stream. The content type comes from the file name.

```php
use Agilis\Ads\Upload;

$offer = $agilis->offers->create([
    'category_id' => 1,
    'title' => '20% off summer sale',
    'description' => 'Discount valid through July',
    'push_notification_title' => '20% off this summer',
    'push_notification_message' => 'Show this at the till for 20% off everything until the end of July.',
    'button_preset' => 'book_now',
    'url' => 'https://example.com/summer',
    'image' => '/path/to/offer.png',            // or Upload::fromBytes($bytes, 'offer.png')
    'location' => [51.5074, -0.1278],
    'radius' => 10,
    'gender' => ['Male', 'Female'],
    'interests' => [3, 7],
    'start_datetime' => new DateTimeImmutable('+1 hour'),
    'expiry_datetime' => new DateTimeImmutable('+8 days'),
    'payment_method' => 'B',
]);
```

Lists go as repeated `location[]`, `gender[]` and `interests[]` parts, and a `crop` array (`['x' => 0, 'y' => 120, 'w' => 1920]`) as `crop[x]`, `crop[y]` and `crop[w]`.

### Publishing and editing an Event

```php
$preview = $agilis->events->preview([
    'title' => 'Beginners Cuban Salsa: Friday Social',
    'description' => 'Drop-in class for absolute beginners, followed by a social until late.',
    'venue_name' => 'Bar Salsa',
    'address' => '96 Charing Cross Road',
    'city' => 'London',
    'country_code' => 'GB',
    'location' => [51.5133, -0.1300],
    'starts_at' => '2027-10-16T18:30:00Z',
    'ends_at' => '2027-10-16T23:00:00Z',
    'url' => 'https://example.com/classes/friday',
    'image' => Upload::fromPath('/path/to/salsa.jpg'),
    'crop' => ['x' => 0, 'y' => 120, 'w' => 1920],
]);

$event = $agilis->events->publish([
    // ... the same fields, with the preview's token instead of the file:
    'preview_token' => $preview['image']['preview_token'],
]);

$agilis->events->edit($event['event_id'], ['title' => 'Cuban Salsa: Friday Social']);
```

`payment_method` defaults to `B` (the balance), the only method for Events. An edit is always sent as a multipart `POST` with `X-HTTP-Method-Override: PATCH`, with or without a new image.

### Pagination

`offers->list()`, `events->list()` and `webhookEvents->list()` return one page, an `Agilis\Ads\Page`:

```php
$page = $agilis->offers->list(['limit' => 20, 'offer_status' => 'active']);

foreach ($page->items() as $offer) {
    echo $offer['offer_id'], "\n";
}

if ($page->hasMore()) {
    $next = $agilis->offers->list(['limit' => 20, 'cursor' => $page->nextCursor()]);
}
```

`listAutoPaging()` follows `next_cursor` for you, one page at a time, until `has_more` is false:

```php
foreach ($agilis->events->listAutoPaging(['event_status' => 'upcoming']) as $event) {
    echo $event['event_id'], ' ', $event['title'], "\n";
}
```

## Idempotency, dry runs and retries

**Idempotency.** `offers->create()` and `events->publish()` always send an `Idempotency-Key`: yours, or a random UUID v4 made once per call and reused on every retry of that call, so a retry never creates a second Offer or charges twice. Pass your own to make a retry safe across processes:

```php
$agilis->offers->create($params, idempotencyKey: $orderId);
```

**Dry runs.** `offers->create()`, `events->publish()`, `offers->delete()`, `events->delete()` and `events->cancel()` take `dryRun: true`, which sends `dry_run=true`: the API checks everything and answers with what would happen, without creating, changing or charging anything.

```php
$check = $agilis->offers->create($params, dryRun: true);
echo $check['price'], ' ', $check['currency'];
```

**Retries.** Up to `maxRetries` (default 2) for:

- connection errors and timeouts;
- HTTP 429;
- HTTP 500, 502, 503 and 504 for `GET` and `DELETE`, and for `POST`s that carry an `Idempotency-Key`.

Other `POST` and `PATCH` requests are not retried on a 5xx, because the change may have happened. The wait is the `Retry-After` header when the API sends one; otherwise 0.5 seconds × 2^attempt with ±25% jitter, at most 8 seconds.

The API allows 60 requests a minute per key. Every response carries `X-RateLimit-Limit` and `X-RateLimit-Remaining`.

## Errors

Every error is an `Agilis\Ads\Exception\AgilisException` carrying `httpStatus`, `returnCode`, the message, the parsed `body` and the response `headers`.

| Exception | When |
|---|---|
| `AuthenticationException` | 401 |
| `PermissionException` | 403 |
| `NotFoundException` | 404 |
| `ConflictException` | 409 |
| `InvalidRequestException` | 400, 422, or HTTP 200 with a `return_code` starting with `E` |
| `RateLimitException` | 429, with `retryAfter` in seconds |
| `ApiException` | 5xx and anything else |
| `ConnectionException` | no response |
| `SignatureVerificationException` | `webhooks->constructEvent()` |

Some refusals arrive as HTTP 200 with an `E` code, for example `E3` when the balance is too low for an Offer. These throw `InvalidRequestException` too, with `httpStatus` 200. `S1` and `S2` are both success. Return codes are scoped to the endpoint: see the [return codes](https://www.agilis.dating/api-doc) for each one.

```php
use Agilis\Ads\Exception\AgilisException;
use Agilis\Ads\Exception\InvalidRequestException;
use Agilis\Ads\Exception\RateLimitException;

try {
    $agilis->offers->create($params);
} catch (InvalidRequestException $e) {
    echo "Refused ({$e->returnCode}): {$e->getMessage()}\n";
} catch (RateLimitException $e) {
    echo "Slow down; retry in {$e->retryAfter} seconds\n";
} catch (AgilisException $e) {
    echo "HTTP {$e->httpStatus}: {$e->getMessage()}\n";
}
```

## Webhooks

Agilis signs every webhook with the `Agilis-Signature` header. `constructEvent()` checks it against your endpoint's secret, in constant time, accepting any matching `v1` signature (there are two while a rolled secret is still valid) and refusing a timestamp more than 5 minutes from now. It returns an `Agilis\Ads\WebhookEvent` or throws `SignatureVerificationException`, whose `reason` is `header`, `timestamp`, `signature` or `payload`.

Pass the body exactly as received, before any JSON parsing. A receiver needs no API key:

```php
<?php

require 'vendor/autoload.php';

use Agilis\Ads\Exception\SignatureVerificationException;
use Agilis\Ads\Webhooks;

$payload = file_get_contents('php://input');
$header = $_SERVER['HTTP_AGILIS_SIGNATURE'] ?? '';

try {
    $event = (new Webhooks())->constructEvent($payload, $header, (string) getenv('AGILIS_WEBHOOK_SECRET'));
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

http_response_code(200); // answer within 10 seconds, then do the work

match ($event->type) {
    'offer.started' => handleOfferStarted($event->object()),
    'event.published' => handleEventPublished($event->object()),
    default => null,
};
```

`WebhookEvent` has `id`, `type`, `created`, `livemode`, `version`, `data`, `request` and `raw` (the whole message), with `object()` for `data.object` and `resource()` for what it is about (`offer`, `event`, `balance`, `invoice` or `terms`). In a framework, use its raw-body accessor, for example `$request->getContent()` in Laravel or Symfony. With a client, `$agilis->webhooks->constructEvent(...)` is the same.

An event can arrive more than once and out of order: use `id` to ignore repeats and `created` to order them.

## Examples

- [`examples/test-mode.php`](examples/test-mode.php): categories, a price estimate, an Offer dry run and auto-paging, in Test mode.
- [`examples/webhook-receiver.php`](examples/webhook-receiver.php): a minimal webhook receiver.

## Development

```bash
composer install
composer test        # unit tests
composer analyse     # PHPStan
composer cs          # coding standard
```

`composer test:integration` runs read-only and dry-run calls against the API with a Test key in `AGILIS_TEST_API_KEY`. It is skipped without one and refuses a Live key.

## Links

- API reference: https://www.agilis.dating/api-doc (Markdown: https://www.agilis.dating/api-doc.md)
- OpenAPI: https://www.agilis.dating/openapi.json
- AsyncAPI (webhooks): https://www.agilis.dating/asyncapi.json
- The API's public repository: https://github.com/agilisdating/agilis-ads-api

## Security

See [SECURITY.md](SECURITY.md) to report a problem or if a key is exposed.

## Licence

MIT. See [LICENSE](LICENSE).
