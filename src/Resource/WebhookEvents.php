<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;
use Agilis\Ads\Page;

/**
 * Webhook events of the last 30 days, in the API key's mode, with their deliveries.
 *
 * @phpstan-type ListWebhookEventsParams array{type?: string, delivery_success?: bool, limit?: int, cursor?: string}
 * @phpstan-type ResendParams array{endpoint_id?: string}
 */
final class WebhookEvents extends AbstractResource
{
    /**
     * GET /webhook-events: one page, newest first.
     *
     * @param ListWebhookEventsParams $params
     */
    public function list(array $params = []): Page
    {
        return $this->getPage('/webhook-events', $params);
    }

    /**
     * Every webhook event matching the filters, following `next_cursor` page by page.
     *
     * @param ListWebhookEventsParams $params
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAutoPaging(array $params = []): \Generator
    {
        return $this->autoPage('/webhook-events', $params);
    }

    /**
     * GET /webhook-events/{webhook_event_id}.
     */
    public function get(string $webhookEventId): ApiResponse
    {
        return $this->getJson('/webhook-events/' . self::segment($webhookEventId));
    }

    /**
     * POST /webhook-events/{webhook_event_id}/resend: to every endpoint, or to `endpoint_id` only.
     *
     * @param ResendParams $params
     */
    public function resend(string $webhookEventId, array $params = []): ApiResponse
    {
        return $this->sendJson('POST', '/webhook-events/' . self::segment($webhookEventId) . '/resend', $params);
    }
}
