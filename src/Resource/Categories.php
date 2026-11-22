<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;

/**
 * Offer categories.
 */
final class Categories extends AbstractResource
{
    /**
     * GET /categories: the categories an Offer can be created in, under `categories`.
     */
    public function list(): ApiResponse
    {
        return $this->getJson('/categories');
    }
}
