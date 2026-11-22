<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;

/**
 * Currencies accepted for Event ticket prices.
 */
final class EventCurrencies extends AbstractResource
{
    /**
     * GET /event-currencies: the accepted ticket-price currencies, under `currencies`.
     */
    public function list(): ApiResponse
    {
        return $this->getJson('/event-currencies');
    }
}
