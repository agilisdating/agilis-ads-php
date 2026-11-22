<?php

/**
 * A minimal webhook receiver. Serve it with any PHP web server, for example:
 *
 *     AGILIS_WEBHOOK_SECRET=whsec_... php -S localhost:8000 examples/webhook-receiver.php
 *
 * then point a Test-mode webhook endpoint at it and send a test event.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Agilis\Ads\Exception\SignatureVerificationException;
use Agilis\Ads\Webhooks;

$secret = getenv('AGILIS_WEBHOOK_SECRET');
$payload = file_get_contents('php://input');
$header = $_SERVER['HTTP_AGILIS_SIGNATURE'] ?? '';

if (!is_string($secret) || $secret === '' || $payload === false || !is_string($header)) {
    http_response_code(500);
    exit;
}

try {
    $event = (new Webhooks())->constructEvent($payload, $header, $secret);
} catch (SignatureVerificationException) {
    http_response_code(400);
    exit;
}

// Answer quickly; do slow work afterwards (for example in a queue). Use $event->id to ignore repeats.
http_response_code(200);

switch ($event->type) {
    case 'offer.started':
        error_log('Offer started: ' . json_encode($event->object()['offer_id'] ?? null));
        break;
    case 'event.published':
        error_log('Event published: ' . json_encode($event->object()['event_id'] ?? null));
        break;
    default:
        error_log('Received ' . $event->type);
}
