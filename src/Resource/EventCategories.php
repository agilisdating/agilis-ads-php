<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;

/**
 * Event categories.
 */
final class EventCategories extends AbstractResource
{
    /**
     * GET /event-categories: the categories an Event can be given, under `categories`.
     */
    public function list(): ApiResponse
    {
        return $this->getJson('/event-categories');
    }
}
