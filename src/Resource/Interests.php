<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;

/**
 * Member interests, for Offer targeting.
 */
final class Interests extends AbstractResource
{
    /**
     * GET /interests: the interests an Offer can target.
     */
    public function list(): ApiResponse
    {
        return $this->getJson('/interests');
    }
}
