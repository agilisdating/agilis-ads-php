<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;

/**
 * What publishing an Event costs, the balance and the image limits.
 */
final class EventConfig extends AbstractResource
{
    /**
     * GET /event-config.
     */
    public function get(): ApiResponse
    {
        return $this->getJson('/event-config');
    }
}
