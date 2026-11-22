<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;
use Agilis\Ads\Page;
use Agilis\Ads\Upload;
use Psr\Http\Message\StreamInterface;

/**
 * Events: preview, publish, list, get, edit, reschedule, cancel, delete and analytics.
 *
 * Dates are ISO 8601 strings in UTC (`2027-10-16T18:30:00Z`) or `DateTimeInterface` objects.
 * `environment` is filled in from the API key when you leave it out.
 *
 * @phpstan-type Crop array{x: int, y: int, w: int}
 * @phpstan-type PublishEventParams array{
 *     title: string,
 *     description: string,
 *     venue_name: string,
 *     address: string,
 *     city: string,
 *     country_code: string,
 *     location: array{0: float|int, 1: float|int},
 *     starts_at: string|\DateTimeInterface,
 *     ends_at: string|\DateTimeInterface,
 *     url: string,
 *     advertised_name?: string,
 *     price_min?: float|int,
 *     price_max?: float|int,
 *     currency?: string,
 *     image?: Upload|string|StreamInterface|resource,
 *     preview_token?: string,
 *     source_event_id?: int,
 *     crop?: Crop,
 *     payment_method?: 'B',
 *     environment?: 'TEST'|'LIVE',
 * }
 * @phpstan-type PreviewEventParams array{
 *     title?: string,
 *     description?: string,
 *     venue_name?: string,
 *     address?: string,
 *     city?: string,
 *     country_code?: string,
 *     location?: array{0: float|int, 1: float|int},
 *     starts_at?: string|\DateTimeInterface,
 *     ends_at?: string|\DateTimeInterface,
 *     url?: string,
 *     advertised_name?: string,
 *     price_min?: float|int,
 *     price_max?: float|int,
 *     currency?: string,
 *     image?: Upload|string|StreamInterface|resource,
 *     crop?: Crop,
 *     payment_method?: 'B',
 *     environment?: 'TEST'|'LIVE',
 * }
 * @phpstan-type EditEventParams array{
 *     title?: string,
 *     description?: string,
 *     url?: string,
 *     advertised_name?: string,
 *     venue_name?: string,
 *     address?: string,
 *     city?: string,
 *     country_code?: string,
 *     location?: array{0: float|int, 1: float|int},
 *     price_min?: float|int|string,
 *     price_max?: float|int|string,
 *     currency?: string,
 *     image?: Upload|string|StreamInterface|resource,
 *     preview_token?: string,
 *     source_event_id?: int,
 *     crop?: Crop,
 * }
 * @phpstan-type ListEventsParams array{
 *     environment?: 'test'|'live'|'TEST'|'LIVE',
 *     limit?: int,
 *     cursor?: string,
 *     event_status?: 'upcoming'|'scheduled'|'rescheduled'|'cancelled'|'ended',
 *     owner_user_id?: int,
 *     created_after?: string|\DateTimeInterface,
 *     created_before?: string|\DateTimeInterface,
 * }
 * @phpstan-type RescheduleParams array{starts_at: string|\DateTimeInterface, ends_at: string|\DateTimeInterface}
 * @phpstan-type AnalyticsParams array{interval: '24h'|'7d'|'30d'|'60d'|'90d'|'all'}
 */
final class Events extends AbstractResource
{
    /**
     * POST /events/preview: the category, fee and processed image an Event would get, without publishing.
     *
     * @param PreviewEventParams $params
     */
    public function preview(array $params): ApiResponse
    {
        return $this->sendMultipart('POST', '/events/preview', $this->withEnvironment($params));
    }

    /**
     * POST /events: publishes an Event, paid from the balance (`payment_method` defaults to `B`).
     *
     * An `Idempotency-Key` is always sent: yours, or a random UUID made once for this call and
     * reused on every retry. `dryRun` checks the Event, the fee and the balance, publishing nothing.
     *
     * @param PublishEventParams $params
     */
    public function publish(array $params, ?string $idempotencyKey = null, bool $dryRun = false): ApiResponse
    {
        $params = $this->withEnvironment($params);
        $params['payment_method'] ??= 'B';

        return $this->sendMultipart(
            'POST',
            '/events',
            $params,
            self::dryRunQuery($dryRun),
            ['Idempotency-Key' => self::idempotencyKey($idempotencyKey)],
        );
    }

    /**
     * GET /events: one page of your Events, newest first.
     *
     * @param ListEventsParams $params
     */
    public function list(array $params = []): Page
    {
        return $this->getPage('/events', $this->withEnvironment($params, upper: false));
    }

    /**
     * Every Event matching the filters, following `next_cursor` page by page.
     *
     * @param ListEventsParams $params
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAutoPaging(array $params = []): \Generator
    {
        return $this->autoPage('/events', $this->withEnvironment($params, upper: false));
    }

    /**
     * GET /events/{event_id}.
     */
    public function get(int $eventId): ApiResponse
    {
        return $this->getJson('/events/' . self::segment($eventId));
    }

    /**
     * PATCH /events/{event_id}: changes the fields you send. Not the dates: use `reschedule()`.
     *
     * Always sent as a multipart POST with `X-HTTP-Method-Override: PATCH`, with or without a new image,
     * because multipart bodies need a POST.
     *
     * @param EditEventParams $params
     */
    public function edit(int $eventId, array $params): ApiResponse
    {
        return $this->sendMultipart(
            'POST',
            '/events/' . self::segment($eventId),
            $params,
            [],
            ['X-HTTP-Method-Override' => 'PATCH'],
        );
    }

    /**
     * POST /events/{event_id}/reschedule.
     *
     * @param RescheduleParams $params
     */
    public function reschedule(int $eventId, array $params): ApiResponse
    {
        return $this->sendJson('POST', '/events/' . self::segment($eventId) . '/reschedule', $params);
    }

    /**
     * POST /events/{event_id}/cancel. `dryRun` checks it can be cancelled without cancelling it.
     */
    public function cancel(int $eventId, bool $dryRun = false): ApiResponse
    {
        return $this->sendJson('POST', '/events/' . self::segment($eventId) . '/cancel', null, self::dryRunQuery($dryRun));
    }

    /**
     * DELETE /events/{event_id}. `dryRun` checks it can be deleted without deleting it.
     */
    public function delete(int $eventId, bool $dryRun = false): ApiResponse
    {
        return $this->sendDelete('/events/' . self::segment($eventId), self::dryRunQuery($dryRun));
    }

    /**
     * GET /events/{event_id}/analytics.
     *
     * @param AnalyticsParams $params
     */
    public function analytics(int $eventId, array $params): ApiResponse
    {
        return $this->getJson('/events/' . self::segment($eventId) . '/analytics', $params);
    }
}
