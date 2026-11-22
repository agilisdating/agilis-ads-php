<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;

/**
 * Webhook endpoints, in the API key's mode. Org Admin and Developer keys only.
 *
 * @phpstan-type CreateEndpointParams array{url: string, enabled_events: list<string>, description?: string}
 * @phpstan-type UpdateEndpointParams array{
 *     url?: string,
 *     enabled_events?: list<string>,
 *     description?: string,
 *     status?: 'enabled'|'disabled',
 * }
 * @phpstan-type RollSecretParams array{expires_in?: 'now'|'1h'|'24h'|'7d'}
 * @phpstan-type SendTestParams array{type?: string}
 */
final class WebhookEndpoints extends AbstractResource
{
    /**
     * GET /webhook-endpoints.
     */
    public function list(): ApiResponse
    {
        return $this->getJson('/webhook-endpoints');
    }

    /**
     * POST /webhook-endpoints. The response holds the endpoint's signing `secret`, shown only this once.
     *
     * @param CreateEndpointParams $params
     */
    public function create(array $params): ApiResponse
    {
        return $this->sendJson('POST', '/webhook-endpoints', $params);
    }

    /**
     * GET /webhook-endpoints/{endpoint_id}.
     */
    public function get(string $endpointId): ApiResponse
    {
        return $this->getJson('/webhook-endpoints/' . self::segment($endpointId));
    }

    /**
     * PATCH /webhook-endpoints/{endpoint_id}.
     *
     * @param UpdateEndpointParams $params
     */
    public function update(string $endpointId, array $params): ApiResponse
    {
        return $this->sendJson('PATCH', '/webhook-endpoints/' . self::segment($endpointId), $params);
    }

    /**
     * DELETE /webhook-endpoints/{endpoint_id}.
     */
    public function delete(string $endpointId): ApiResponse
    {
        return $this->sendDelete('/webhook-endpoints/' . self::segment($endpointId));
    }

    /**
     * POST /webhook-endpoints/{endpoint_id}/roll-secret. The new secret is in the response, shown only this once.
     *
     * @param RollSecretParams $params
     */
    public function rollSecret(string $endpointId, array $params = []): ApiResponse
    {
        return $this->sendJson('POST', '/webhook-endpoints/' . self::segment($endpointId) . '/roll-secret', $params);
    }

    /**
     * POST /webhook-endpoints/{endpoint_id}/test: sends a test event to the endpoint.
     *
     * @param SendTestParams $params
     */
    public function sendTest(string $endpointId, array $params = []): ApiResponse
    {
        return $this->sendJson('POST', '/webhook-endpoints/' . self::segment($endpointId) . '/test', $params);
    }
}
