<?php

/**
 * A short tour in Test mode. Run with a Test key:
 *
 *     AGILIS_API_KEY=pk_test_... php examples/test-mode.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Agilis\Ads\AgilisClient;
use Agilis\Ads\Exception\AgilisException;
use Agilis\Ads\Upload;

$agilis = new AgilisClient(); // reads AGILIS_API_KEY

if ($agilis->isLive()) {
    fwrite(STDERR, "This example only runs with a Test key (pk_test_).\n");
    exit(1);
}

try {
    $categories = $agilis->categories->list();
    $categoryList = is_array($categories['categories']) ? $categories['categories'] : [];
    echo 'Categories: ', count($categoryList), "\n";

    $estimate = $agilis->offers->estimatePrice([
        'start_datetime' => new DateTimeImmutable('+1 day'),
        'end_datetime' => new DateTimeImmutable('+8 days'),
        'location' => [51.5074, -0.1278],
        'radius' => 10,
    ]);
    echo 'Estimated cost: ', json_encode($estimate['estimated_cost']), ' ', json_encode($estimate['currency']), "\n";

    // Check a whole Offer without creating it.
    $first = $categoryList[0] ?? [];
    $dryRun = $agilis->offers->create([
        'category_id' => is_array($first) && is_int($first['category_id'] ?? null) ? $first['category_id'] : 1,
        'title' => '20% off this week',
        'description' => 'Show this offer at the till.',
        'push_notification_title' => '20% off this week',
        'push_notification_message' => 'Show this at the till for 20% off.',
        'url' => 'https://example.com/offer',
        'image' => Upload::fromPath(__DIR__ . '/../tests/Fixtures/sample-offer.png'),
        'location' => [51.5074, -0.1278],
        'radius' => 10,
        'start_datetime' => new DateTimeImmutable('+1 day'),
        'expiry_datetime' => new DateTimeImmutable('+8 days'),
        'payment_method' => 'B',
    ], dryRun: true);
    echo 'Dry run price: ', json_encode($dryRun['price']), "\n";

    // Every Offer, page by page.
    foreach ($agilis->offers->listAutoPaging(['limit' => 50]) as $offer) {
        echo '- ', json_encode($offer['offer_id']), ' ', json_encode($offer['title']), ' (', json_encode($offer['offer_status']), ")\n";
    }
} catch (AgilisException $e) {
    fwrite(STDERR, sprintf("Refused: %s (HTTP %s, %s)\n", $e->getMessage(), $e->httpStatus ?? 'none', $e->returnCode ?? 'no code'));
    exit(1);
}
