<?php

declare(strict_types=1);

namespace Agilis\Ads\Resource;

use Agilis\Ads\ApiResponse;
use Agilis\Ads\Page;
use Agilis\Ads\Upload;
use Psr\Http\Message\StreamInterface;

/**
 * Offers: estimate, create, list, pause, resume, analytics and delete.
 *
 * Dates are ISO 8601 strings in UTC (`2027-07-10T08:00:00Z`) or `DateTimeInterface` objects.
 * `environment` is filled in from the API key when you leave it out.
 *
 * @phpstan-type Gender 'Male'|'Female'|'Non-binary'|'All'
 * @phpstan-type PriceEstimateParams array{
 *     start_datetime: string|\DateTimeInterface,
 *     end_datetime: string|\DateTimeInterface,
 *     location: array{0: float|int, 1: float|int},
 *     radius: int,
 *     gender?: list<Gender>,
 *     interests?: list<int>,
 *     environment?: 'TEST'|'LIVE'|'test'|'live',
 * }
 * @phpstan-type CreateOfferParams array{
 *     category_id: int,
 *     title: string,
 *     description: string,
 *     terms_and_conditions?: string,
 *     advertised_name?: string,
 *     push_notification_title: string,
 *     push_notification_message: string,
 *     button_preset?: 'claim_offer'|'visit_website'|'install_app'|'book_now'|'get_directions'|'custom',
 *     button_text?: string,
 *     url: string,
 *     image: Upload|string|StreamInterface|resource,
 *     location: array{0: float|int, 1: float|int},
 *     radius: int,
 *     gender?: list<Gender>,
 *     interests?: list<int>,
 *     start_datetime: string|\DateTimeInterface,
 *     expiry_datetime: string|\DateTimeInterface,
 *     payment_method: 'E'|'B',
 *     environment?: 'TEST'|'LIVE',
 * }
 * @phpstan-type ListOffersParams array{
 *     environment?: 'test'|'live'|'TEST'|'LIVE',
 *     limit?: int,
 *     cursor?: string,
 *     offer_status?: 'awaiting_payment'|'active'|'paused'|'ended',
 *     created_after?: string|\DateTimeInterface,
 *     created_before?: string|\DateTimeInterface,
 * }
 * @phpstan-type AnalyticsParams array{interval: '24h'|'7d'|'30d'|'60d'|'90d'|'all'}
 */
final class Offers extends AbstractResource
{
    /**
     * POST /offers/price-estimate: the price and audience of an Offer before creating it.
     *
     * @param PriceEstimateParams $params
     */
    public function estimatePrice(array $params): ApiResponse
    {
        return $this->sendJson('POST', '/offers/price-estimate', $this->withEnvironment($params));
    }

    /**
     * POST /offers: creates an Offer (multipart, with the image).
     *
     * An `Idempotency-Key` is always sent: yours, or a random UUID made once for this call and
     * reused on every retry. `dryRun` checks everything and answers with the price, creating nothing.
     *
     * @param CreateOfferParams $params
     */
    public function create(array $params, ?string $idempotencyKey = null, bool $dryRun = false): ApiResponse
    {
        return $this->sendMultipart(
            'POST',
            '/offers',
            $this->withEnvironment($params),
            self::dryRunQuery($dryRun),
            ['Idempotency-Key' => self::idempotencyKey($idempotencyKey)],
        );
    }

    /**
     * GET /offers: one page of your Offers, newest first.
     *
     * @param ListOffersParams $params
     */
    public function list(array $params = []): Page
    {
        return $this->getPage('/offers', $this->withEnvironment($params, upper: false));
    }

    /**
     * Every Offer matching the filters, following `next_cursor` page by page.
     *
     * @param ListOffersParams $params
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAutoPaging(array $params = []): \Generator
    {
        return $this->autoPage('/offers', $this->withEnvironment($params, upper: false));
    }

    /**
     * POST /offers/{offer_id}/pause.
     */
    public function pause(int $offerId): ApiResponse
    {
        return $this->sendJson('POST', '/offers/' . self::segment($offerId) . '/pause', null);
    }

    /**
     * POST /offers/{offer_id}/resume. Success is `S2`.
     */
    public function resume(int $offerId): ApiResponse
    {
        return $this->sendJson('POST', '/offers/' . self::segment($offerId) . '/resume', null);
    }

    /**
     * GET /offers/{offer_id}/analytics.
     *
     * @param AnalyticsParams $params
     */
    public function analytics(int $offerId, array $params): ApiResponse
    {
        return $this->getJson('/offers/' . self::segment($offerId) . '/analytics', $params);
    }

    /**
     * DELETE /offers/{offer_id}. `dryRun` says what deleting would do, including any credit, without deleting.
     */
    public function delete(int $offerId, bool $dryRun = false): ApiResponse
    {
        return $this->sendDelete('/offers/' . self::segment($offerId), self::dryRunQuery($dryRun));
    }
}
